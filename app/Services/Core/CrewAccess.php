<?php

namespace App\Services\Core;

use App\Models\CrewMember;
use App\Models\OperationalFlight;
use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CrewAccess
{
    public static function member(string $action, CrewMember $crew): void
    {
        if (Auth::check()) {
            Gate::authorize('crew.'.$action, [['airport' => $crew->base_airport_code]]);
        }
    }

    public static function flight(string $action, OperationalFlight $flight): void
    {
        if (Auth::check()) {
            Gate::authorize('crew.'.$action, [['airport' => $flight->route->origin_airport_code, 'flight' => $flight->id]]);
        }
    }

    public static function profiles($query)
    {
        if (! Auth::check()) {
            return $query;
        }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), 'crew.read');

        return $scopes->contains('scope_type', 'global') ? $query : $query->whereIn('base_airport_code', $scopes->where('scope_type', 'airport')->pluck('scope_id'));
    }

    public static function flights($query)
    {
        if (! Auth::check()) {
            return $query;
        }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), 'crew.read');

        return $scopes->contains('scope_type', 'global') ? $query : $query->where(fn ($q) => $q
            ->whereIn('id', $scopes->where('scope_type', 'flight')->pluck('scope_id'))
            ->orWhereHas('route', fn ($r) => $r->whereIn('origin_airport_code', $scopes->where('scope_type', 'airport')->pluck('scope_id'))));
    }
}
