<?php

namespace App\Services\Core;

use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FleetAccess
{
    public static function authorize(string $action, ?string $airport = null): void
    {
        if (Auth::check()) {
            Gate::authorize('aircraft.'.$action, $airport ? [['airport' => $airport]] : []);
        }
    }

    public static function filter($query)
    {
        if (! Auth::check()) {
            return $query;
        }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), 'aircraft.read');

        return $scopes->contains('scope_type', 'global') ? $query
            : $query->whereIn('base_airport_code', $scopes->where('scope_type', 'airport')->pluck('scope_id'));
    }
}
