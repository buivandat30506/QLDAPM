<?php

namespace App\Shared\Security;

use App\Models\OperationalFlight;
use App\Models\PassengerProfile;
use App\Models\User;

class PassengerAccess
{
    public function __construct(private AccessControl $access) {}

    public function owns(User $user, PassengerProfile $passenger, ?string $flightId = null): bool
    {
        if ($passenger->user_id !== null) {
            return $passenger->user_id === $user->id;
        }
        if ($passenger->created_by === $user->id) {
            return true;
        }

        return $passenger->orderItems()
            ->when($flightId, fn ($query) => $query->where('flight_id', $flightId))
            ->whereHas('order', fn ($query) => $query->where('customer_user_id', $user->id))->exists();
    }

    public function allows(User $user, string $permission, PassengerProfile $passenger, ?OperationalFlight $flight = null): bool
    {
        $context = [];
        if ($this->owns($user, $passenger, $flight?->id)) {
            $context['self'] = $user->id;
        }
        if ($flight) {
            $context['flight'] = $flight->id;
            $context['airport'] = $flight->route->origin_airport_code;
        }

        return $this->access->allows($user, $permission, $context);
    }
}
