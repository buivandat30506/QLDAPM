<?php

namespace App\Services\Passenger;

use App\Models\AssistanceRequest;
use App\Models\OperationalFlight;
use App\Models\PassengerProfile;
use App\Models\User;
use App\Services\Core\AuditService;
use App\Shared\Security\PassengerAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssistanceService
{
    public function visible(User $actor)
    {
        $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($actor, 'assistance.read');
        abort_if($scopes->isEmpty(), 403);
        $query = AssistanceRequest::with(['passenger', 'flight']);
        if ($scopes->contains('scope_type', 'global')) { return $query; }

        return $query->where(function ($q) use ($actor, $scopes) {
            foreach ($scopes as $scope) {
                match ($scope->scope_type) {
                    'self' => $q->orWhereHas('passenger', fn ($profile) => $profile->where('user_id', $actor->id)->orWhere(fn ($guest) => $guest->whereNull('user_id')->where(fn ($owned) => $owned->where('created_by', $actor->id)->orWhereHas('orderItems.order', fn ($order) => $order->where('customer_user_id', $actor->id))))),
                    'airport' => $q->orWhereHas('flight.route', fn ($route) => $route->where('origin_airport_code', $scope->scope_id)),
                    'flight' => $q->orWhere('flight_id', $scope->scope_id),
                    default => null,
                };
            }
        });
    }

    public function allows(User $actor, AssistanceRequest $request, string $permission): bool
    {
        return app(PassengerAccess::class)->allows($actor, $permission, $request->passenger, $request->flight);
    }

    public function create(User $actor, array $data): AssistanceRequest
    {
        Validator::make($data, ['passenger_id' => 'required|uuid', 'flight_id' => 'nullable|uuid', 'category' => 'required|in:WHEELCHAIR,MEDICAL,UNACCOMPANIED_MINOR,OTHER', 'details' => 'required|string|max:2000'])->validate();
        $profile = PassengerProfile::findOrFail($data['passenger_id']);
        $flight = empty($data['flight_id']) ? null : OperationalFlight::findOrFail($data['flight_id']);
        abort_unless(app(PassengerAccess::class)->allows($actor, 'assistance.write', $profile, $flight), 404);
        if ($flight && ! $profile->orderItems()->where('flight_id', $flight->id)->whereHas('order', fn ($q) => $q->where('order_status', 'CONFIRMED')->orWhere(fn ($held) => $held->where('order_status', 'HOLDING')->where('hold_expires_at', '>', now('UTC'))))->exists()) {
            throw ValidationException::withMessages(['flight_id' => ['Hồ sơ chưa có đặt chỗ còn hiệu lực cho chuyến đã chọn. Có thể gửi hỗ trợ chung không gắn chuyến.']]);
        }

        return DB::transaction(function () use ($actor, $data, $profile, $flight) {
            $request = AssistanceRequest::create(['passenger_id' => $profile->id, 'flight_id' => $flight?->id, 'created_by' => $actor->id, 'category' => $data['category'], 'details' => $data['details'], 'department' => $flight ? 'AIRPORT_SUPPORT' : 'CUSTOMER_CARE', 'status' => 'ROUTED']);
            AuditService::record('assistance_requests', $request->id, 'ASSISTANCE_ROUTED', null, ['category' => $request->category, 'department' => $request->department, 'status' => $request->status]);

            return $request;
        });
    }

    public function act(User $actor, string $id, array $data): AssistanceRequest
    {
        Validator::make($data, ['action' => 'required|in:update,cancel,route,complete', 'details' => 'required_if:action,update|string|max:2000', 'department' => 'required_if:action,route|in:AIRPORT_SUPPORT,CUSTOMER_CARE', 'resolution' => 'required_if:action,complete|string|max:2000'])->validate();

        return DB::transaction(function () use ($actor, $id, $data) {
            $request = AssistanceRequest::lockForUpdate()->findOrFail($id);
            $permission = in_array($data['action'], ['route', 'complete']) ? 'assistance.manage' : 'assistance.write';
            abort_unless($this->allows($actor, $request, $permission), 404);
            if (in_array($request->status, ['CANCELLED', 'COMPLETED'])) {
                throw ValidationException::withMessages(['status' => ['Yêu cầu đã kết thúc; không thể sửa trạng thái.']]);
            }
            match ($data['action']) {
                'update' => $request->details = $data['details'],
                'cancel' => $request->status = 'CANCELLED',
                'route' => $request->department = $data['department'],
                'complete' => $request->fill(['status' => 'COMPLETED', 'resolution' => $data['resolution']]),
            };
            $request->save();
            AuditService::record('assistance_requests', $request->id, 'ASSISTANCE_'.strtoupper($data['action']), null, ['status' => $request->status, 'department' => $request->department]);

            return $request;
        });
    }
}
