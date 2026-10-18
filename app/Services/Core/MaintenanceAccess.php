<?php

namespace App\Services\Core;

use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MaintenanceAccess
{
    public static function authorize(string $action, ?string $airport): void
    {
        if (Auth::check()) { Gate::authorize('maintenance.'.$action, $airport ? [['airport' => $airport]] : []); }
    }

    public static function filter($query, string $column = 'a.base_airport_code')
    {
        if (! Auth::check()) { return $query; }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), 'maintenance.read');
        return $scopes->contains('scope_type', 'global') ? $query : $query->whereIn($column, $scopes->where('scope_type', 'airport')->pluck('scope_id'));
    }
}
