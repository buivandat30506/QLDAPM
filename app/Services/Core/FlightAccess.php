<?php

namespace App\Services\Core;

use App\Models\OperationalFlight;
use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FlightAccess
{
    public static function authorize(string $action, OperationalFlight $flight): void
    {
        if (Auth::check()) { Gate::authorize('flight.'.$action, [['airport' => $flight->route->origin_airport_code, 'flight' => $flight->id]]); }
    }

    public static function query($query, string $action = 'read')
    {
        if (! Auth::check()) { return $query; }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), 'flight.'.$action);
        return $scopes->contains('scope_type', 'global') ? $query : $query->where(fn ($q) => $q
            ->whereIn('id', $scopes->where('scope_type', 'flight')->pluck('scope_id'))
            ->orWhereHas('route', fn ($r) => $r->whereIn('origin_airport_code', $scopes->where('scope_type', 'airport')->pluck('scope_id'))));
    }
}
