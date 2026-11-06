<?php

namespace App\Services\Passenger;

use App\Models\PassengerProfile;
use App\Models\User;
use App\Services\Core\AuditService;
use App\Shared\Security\AccessControl;
use App\Shared\Security\PassengerAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PassengerProfileService
{
    public function visible(User $actor)
    {
        $query = PassengerProfile::query();
        if (! app(AccessControl::class)->allows($actor, 'passenger.read')) {
            abort_unless(app(AccessControl::class)->allows($actor, 'passenger.read', ['self' => $actor->id]), 403);
            $query->where(function ($q) use ($actor) {
                $q->where('user_id', $actor->id)->orWhere(fn ($guest) => $guest->whereNull('user_id')->where(fn ($owned) => $owned->where('created_by', $actor->id)
                    ->orWhereHas('orderItems.order', fn ($order) => $order->where('customer_user_id', $actor->id))));
            });
        }

        return $query;
    }

    public function save(User $actor, array $data, ?string $id = null): PassengerProfile
    {
        $profile = $id ? PassengerProfile::findOrFail($id) : null;
        abort_unless($profile ? app(PassengerAccess::class)->allows($actor, 'passenger.write', $profile)
            : app(AccessControl::class)->allows($actor, 'passenger.write', ['self' => $actor->id]), $id ? 404 : 403);
        if (! empty($data['id_card_passport'])) { $data['id_card_passport'] = strtoupper(trim($data['id_card_passport'])); }
        Validator::make($data, [
            'full_name' => 'required|string|max:100', 'id_card_passport' => [$id ? 'nullable' : 'required', 'string', 'max:50', Rule::unique('passenger_profiles')->ignore($id)],
            'dob' => 'required|date_format:Y-m-d|before_or_equal:today', 'gender' => 'required|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:100',
            'is_self' => 'nullable|boolean', 'consent' => [$id ? 'sometimes' : 'required', 'accepted'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data, $id) {
            if (! $id) { User::whereKey($actor->id)->lockForUpdate()->firstOrFail(); }
            $profile = $id ? PassengerProfile::lockForUpdate()->findOrFail($id) : new PassengerProfile;
            if ($id) { abort_unless(app(PassengerAccess::class)->allows($actor, 'passenger.write', $profile), 404); }
            $values = array_intersect_key($data, array_flip(['full_name', 'id_card_passport', 'dob', 'gender', 'phone', 'email']));
            if (empty($values['id_card_passport'])) { unset($values['id_card_passport']); }
            if (! $id) {
                $values['created_by'] = $actor->id;
                $values['user_id'] = ! empty($data['is_self']) ? $actor->id : null;
                if ($values['user_id'] && PassengerProfile::where('user_id', $actor->id)->exists()) {
                    throw ValidationException::withMessages(['is_self' => ['Tài khoản đã có hồ sơ cá nhân. Thêm người đi cùng với lựa chọn hồ sơ cá nhân bỏ chọn.']]);
                }
            }
            $profile->fill($values)->save();
            if (! $id || ! empty($data['consent'])) { $this->consent($actor, $profile, ['purpose' => 'SERVICE', 'granted' => true]); }
            AuditService::record('passenger_profiles', $profile->id, $id ? 'PROFILE_UPDATED' : 'PROFILE_CREATED', null, ['fields' => array_keys($values)]);

            return $profile;
        });
    }

    public function consent(User $actor, PassengerProfile $profile, array $data): void
    {
        abort_unless(app(PassengerAccess::class)->allows($actor, 'passenger.write', $profile), 404);
        Validator::make($data, ['purpose' => 'required|in:SERVICE,MARKETING', 'granted' => 'required|boolean'])->validate();
        DB::transaction(function () use ($actor, $profile, $data) {
            DB::table('passenger_consents')->insert(['passenger_id' => $profile->id, 'actor_id' => $actor->id, 'purpose' => $data['purpose'], 'granted' => $data['granted'], 'policy_version' => 'local-policy-v1', 'recorded_at_utc' => now('UTC')]);
            AuditService::record('passenger_profiles', $profile->id, 'CONSENT_RECORDED', null, $data);
        });
    }
}
