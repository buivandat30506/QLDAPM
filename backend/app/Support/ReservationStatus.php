<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class ReservationStatus
{
    /** Reservations have no status column; display the existing aggregate of their initial tickets. */
    public static function fromBookings(object $reservation, Collection $original): string
    {
        if ($original->isEmpty()) {
            return 'PARTIAL';
        }

        return match (true) {
            $original->contains('status', 'PAYMENT_PENDING') => 'PAYMENT_PENDING',
            $original->every(fn ($b) => $b->status === 'HELD') => now()->lt($reservation->expires_at) ? 'HELD' : 'EXPIRED',
            $original->every(fn ($b) => in_array($b->status, ['CONFIRMED', 'CHECKED_IN', 'BOARDED'])) => 'CONFIRMED',
            $original->every(fn ($b) => $b->status === 'REFUNDED') => 'REFUNDED',
            $original->every(fn ($b) => in_array($b->status, ['EXPIRED', 'CANCELLED'])) => $original->contains('status', 'EXPIRED') ? 'EXPIRED' : 'CANCELLED',
            default => 'PARTIAL',
        };
    }
}
