<?php

namespace App\Services\Core;

use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PlanningAccess
{
    public static function authorize(string $permission, string $airport): void
    {
        if (Auth::check()) { Gate::authorize($permission, [['airport' => $airport]]); }
    }

    public static function filter($query, string $permission, string $column)
    {
        if (! Auth::check()) { return $query; }
        $scopes = app(AccessControl::class)->scopes(Auth::user(), $permission);
        if ($scopes->contains('scope_type', 'global')) { return $query; }

        return $query->whereIn($column, $scopes->where('scope_type', 'airport')->pluck('scope_id'));
    }
}
