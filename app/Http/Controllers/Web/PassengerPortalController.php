<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\PassengerProfile;
use App\Services\Core\AuditService;
use App\Services\Passenger\AssistanceService;
use App\Services\Passenger\PassengerProfileService;
use App\Shared\Security\PassengerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerPortalController extends Controller
{
    public function profiles(Request $request, PassengerProfileService $service)
    {
        $request->validate(['search' => 'nullable|string|max:100']);
        $profiles = $service->visible($request->user())->when($request->search, fn ($q) => $q->where('full_name', 'like', '%'.$request->search.'%'))->orderBy('full_name')->paginate(25)->withQueryString();

        return view('passenger.profiles', compact('profiles'));
    }

    public function profile(Request $request, string $id)
    {
        $profile = PassengerProfile::findOrFail($id);
        abort_unless(app(PassengerAccess::class)->allows($request->user(), 'passenger.read', $profile), 404);
        AuditService::record('passenger_profiles', $id, 'SENSITIVE_READ');

        return view('passenger.profile-edit', ['profile' => $profile, 'canWrite' => app(PassengerAccess::class)->allows($request->user(), 'passenger.write', $profile), 'consents' => DB::table('passenger_consents')->where('passenger_id', $id)->orderByDesc('id')->get()]);
    }

    public function saveProfile(Request $request, PassengerProfileService $service, ?string $id = null)
    {
        $profile = $service->save($request->user(), $request->all(), $id);

        return redirect()->route('passenger.profiles.show', $profile->id)->with('status', 'Đã lưu hồ sơ hành khách.');
    }

    public function consent(Request $request, string $id, PassengerProfileService $service)
    {
        $service->consent($request->user(), PassengerProfile::findOrFail($id), $request->all());

        return back()->with('status', 'Đã ghi nhận lựa chọn đồng ý và giữ lịch sử.');
    }

    public function assistance(Request $request, AssistanceService $service, PassengerProfileService $profiles)
    {
        $request->validate(['status' => 'nullable|in:ROUTED,COMPLETED,CANCELLED', 'department' => 'nullable|in:AIRPORT_SUPPORT,CUSTOMER_CARE']);
        $requests = $service->visible($request->user())->when($request->status, fn ($q) => $q->where('status', $request->status))->when($request->department, fn ($q) => $q->where('department', $request->department))->orderByDesc('created_at')->paginate(25)->withQueryString();
        $canCreate = $request->user()->can('assistance.write', [['self' => $request->user()->id]]);
        $visibleProfiles = $canCreate ? $profiles->visible($request->user())->orderBy('full_name')->get() : collect();
        $flights = $canCreate ? \App\Models\OperationalFlight::with('route')->whereHas('orderItems', fn ($q) => $q->whereIn('passenger_id', $visibleProfiles->pluck('id'))->whereHas('order', fn ($order) => $order->where('order_status', 'CONFIRMED')->orWhere(fn ($held) => $held->where('order_status', 'HOLDING')->where('hold_expires_at', '>', now('UTC')))))->orderBy('scheduled_dep_utc')->get() : collect();

        return view('passenger.assistance', ['requests' => $requests, 'profiles' => $visibleProfiles, 'flights' => $flights, 'canCreate' => $canCreate]);
    }

    public function assistanceDetail(Request $request, string $id, AssistanceService $service)
    {
        $assistance = AssistanceRequest::with(['passenger', 'flight'])->findOrFail($id);
        abort_unless($service->allows($request->user(), $assistance, 'assistance.read'), 404);
        AuditService::record('assistance_requests', $id, 'SENSITIVE_READ');

        return view('passenger.assistance-detail', ['assistance' => $assistance, 'canEdit' => $service->allows($request->user(), $assistance, 'assistance.write'), 'canManage' => $service->allows($request->user(), $assistance, 'assistance.manage')]);
    }

    public function createAssistance(Request $request, AssistanceService $service)
    {
        $assistance = $service->create($request->user(), $request->all());

        return redirect()->route('passenger.assistance.show', $assistance->id)->with('status', 'Đã chuyển yêu cầu tới bộ phận phụ trách.');
    }

    public function actAssistance(Request $request, string $id, AssistanceService $service)
    {
        $service->act($request->user(), $id, $request->all());

        return back()->with('status', 'Đã cập nhật yêu cầu hỗ trợ.');
    }
}
