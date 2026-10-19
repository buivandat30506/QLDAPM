<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Core\AuditService;
use Illuminate\Support\Facades\Gate;

class CoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $path = preg_replace('#^(api/v1/core/|admin/)#', '', $request->path());
        $section = explode('/', $path)[0];
        $domain = match ($section) {
            'airports' => 'airport', 'routes' => 'route', 'master-data' => 'master',
            'schedules' => 'schedule', 'flights', 'events' => 'flight',
            'aircrafts', 'aircraft-models' => 'aircraft', 'maintenance' => 'maintenance',
            'crew', 'crew-members', 'crew-assignments', 'crew-plans' => 'crew',
            'dashboard', 'workflows' => 'operations', 'audit' => 'audit',
            'versions' => $request->input('entity_type') === 'flight_schedules' ? 'schedule' : 'master',
            default => null,
        };
        if (str_contains($path, '/crew') || str_starts_with($path, 'crew-assignments')) {
            $domain = 'crew';
        }
        $action = $request->isMethod('GET') ? 'read' : 'write';
        if (preg_match('#/(authorize|approve|publish|release|reject)$#', $path)) {
            $action = 'approve';
        }
        if (str_ends_with($path, '/confirm') && str_contains($path, 'crew-assignments')) {
            $action = 'confirm';
        }
        // Legacy collection endpoints fail closed for limited scopes until G2 migrates object queries.
        $allowed = $domain !== null && Gate::allows($domain.'.'.$action, [['self' => $request->user()?->id]]);
        if ($domain === 'airport') {
            $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($request->user(), 'airport.'.$action);
            $isCreateAirport = $request->isMethod('POST') && trim($path, '/') === 'airports';
            $allowed = $isCreateAirport ? Gate::allows('airport.write')
                : $scopes->contains(fn ($s) => in_array($s->scope_type, ['global', 'airport']));
            $airport = $request->route('iataCode');
            if (! $airport && str_contains($path, 'resources/')) {
                $resourceId = $request->route('resourceId') ?? $request->route('id');
                $airport = \App\Models\AirportResource::whereKey($resourceId)->value('airport_code');
            }
            if ($airport) { $allowed = Gate::allows('airport.'.$action, [['airport' => strtoupper($airport)]]); }
            // AirportService filters collections and checks the specific object's airport.
        }
        if (in_array($domain, ['route', 'schedule'])) {
            if (str_ends_with($path, '/cancel')) { $action = 'approve'; }
            $permission = $domain.'.'.$action;
            $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($request->user(), $permission);
            $allowed = $scopes->contains(fn ($s) => in_array($s->scope_type, ['global', 'airport']));
            $objectId = $request->route('id') ?? ($section === 'versions' ? $request->input('entity_id') : null);
            $airport = null;
            if ($domain === 'route' && $objectId) { $airport = \App\Models\Route::whereKey($objectId)->value('origin_airport_code'); }
            if ($domain === 'schedule' && $objectId) {
                $airport = \App\Models\Route::whereKey(\App\Models\FlightSchedule::whereKey($objectId)->value('route_id'))->value('origin_airport_code');
            }
            if (! $objectId && $request->isMethod('POST')) {
                $airport = $domain === 'route' ? (is_string($request->input('origin_airport_code')) ? strtoupper($request->input('origin_airport_code')) : '')
                    : \App\Models\Route::whereKey(is_scalar($request->input('route_id')) ? $request->input('route_id') : null)->value('origin_airport_code');
                $allowed = Gate::allows($permission, [['airport' => $airport ?? '']]);
            } elseif ($airport) { $allowed = Gate::allows($permission, [['airport' => $airport]]); }
        }
        if ($domain === 'aircraft') {
            $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($request->user(), 'aircraft.'.$action);
            $allowed = $scopes->contains(fn ($s) => in_array($s->scope_type, ['global', 'airport']));
            $tail = $request->route('tailNumber');
            if ($tail) {
                $base = \App\Models\Aircraft::whereKey(strtoupper($tail))->value('base_airport_code');
                $allowed = Gate::allows('aircraft.'.$action, $base ? [['airport' => $base]] : []);
            } elseif (! $request->isMethod('GET')) {
                $base = is_string($request->input('base_airport_code')) ? $request->input('base_airport_code') : null;
                $allowed = Gate::allows('aircraft.'.$action, $section !== 'aircraft-models' && $base ? [['airport' => $base]] : []);
            }
        }
        if ($domain === 'maintenance') {
            if (str_ends_with($path, '/keep-unavailable')) { $action = 'approve'; }
            $permission = 'maintenance.'.$action;
            $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($request->user(), $permission);
            $allowed = $scopes->contains(fn ($s) => in_array($s->scope_type, ['global', 'airport']));
            $plan = $request->route('plan'); $order = $request->route('order'); $evidence = $request->route('evidence');
            if ($evidence) { $order = \Illuminate\Support\Facades\DB::table('maintenance_evidence')->where('id', $evidence)->value('order_id'); }
            if ($request->route('id')) { $order = $request->route('id'); }
            $tail = $order ? \Illuminate\Support\Facades\DB::table('maintenance_orders')->where('id', $order)->value('aircraft_tail')
                : ($plan ? \Illuminate\Support\Facades\DB::table('maintenance_plans')->where('id', $plan)->value('aircraft_tail') : null);
            if ($tail) { $airport = \App\Models\Aircraft::whereKey($tail)->value('base_airport_code'); $allowed = Gate::allows($permission, $airport ? [['airport' => $airport]] : []); }
            elseif (! $request->isMethod('GET') && ! $plan && ! $order && ! $evidence) {
                $inputTail = is_string($request->input('aircraft_tail')) ? strtoupper(trim($request->input('aircraft_tail'))) : null;
                $airport = $inputTail ? \App\Models\Aircraft::whereKey($inputTail)->value('base_airport_code') : $request->input('airport_code');
                $allowed = Gate::allows($permission, is_string($airport) && $airport !== '' ? [['airport' => $airport]] : []);
            }
        }
        if ($domain === 'flight') {
            $permission = 'flight.'.$action;
            $scopes = app(\App\Shared\Security\AccessControl::class)->scopes($request->user(), $permission);
            $allowed = $scopes->contains(fn ($s) => in_array($s->scope_type, ['global','airport','flight']));
            $flightId = $section === 'flights' ? ($request->route('id') ?? $request->route('flightId')) : $request->input('flight_id');
            if ($section === 'events' && $request->route('event')) {
                $flightId = \Illuminate\Support\Facades\DB::table('flight_events')->where('id',$request->route('event'))->value('flight_id');
            }
            if ($flightId) {
                $flight = \App\Models\OperationalFlight::with('route')->find(is_scalar($flightId) ? $flightId : null);
                $allowed = $flight ? Gate::allows($permission, [['airport'=>$flight->route->origin_airport_code,'flight'=>$flight->id]]) : Gate::allows($permission);
            } elseif (!$request->isMethod('GET') && $section === 'flights' && !str_contains($path,'flights/bulk/')) {
                $schedule = \App\Models\FlightSchedule::with('route')->find(is_scalar($request->input('schedule_id')) ? $request->input('schedule_id') : null);
                $allowed = $schedule ? Gate::allows($permission, [['airport'=>$schedule->route->origin_airport_code]]) : Gate::allows($permission);
            }
        }
        if ($domain === 'crew') {
            $permission='crew.'.$action;
            $scopes=app(\App\Shared\Security\AccessControl::class)->scopes($request->user(),$permission);
            $allowed=$scopes->contains(fn($s)=>in_array($s->scope_type,['global','airport']));
            $flightId=$request->route('flightId'); $assignment=null;
            if ($section==='crew-assignments' || str_contains($path,'/crew-assignments/')) {
                $assignment=\App\Models\FlightCrewAssignment::find($request->route('id') ?? $request->route('assignmentId'));
                $flightId=$assignment?->flight_id;
            } elseif ($section==='flights') { $flightId=$flightId ?? $request->route('id'); }
            elseif ($section==='crew-plans') { $allowed=$scopes->contains(fn($s)=>in_array($s->scope_type,['global','airport','flight'])); }
            if ($flightId) {
                $flight=\App\Models\OperationalFlight::with('route')->find($flightId);
                $allowed=$flight && Gate::allows($permission,[['airport'=>$flight->route->origin_airport_code,'flight'=>$flight->id]]);
                if ($action==='confirm' && $assignment) {
                    $allowed=Gate::allows('crew.approve',[['airport'=>$flight?->route?->origin_airport_code,'flight'=>$flightId]])
                        || ($assignment->crewMember->user_id===$request->user()->id && Gate::allows('crew.confirm',[['self'=>$request->user()->id]]));
                }
                if ($allowed && $action==='write' && $request->isMethod('POST') && $request->has('crew_id')) {
                    $crewId=is_string($request->input('crew_id'))?$request->input('crew_id'):null;
                    $base=\App\Models\CrewMember::whereKey($crewId)->value('base_airport_code');
                    $allowed=Gate::allows('crew.write',[['airport'=>$base??'']]);
                }
            } elseif (in_array($section,['crew','crew-members'])) {
                $id=$request->route('id');
                if ($id) {
                    $base=\App\Models\CrewMember::whereKey($id)->value('base_airport_code');
                    $allowed=Gate::allows($permission,[['airport'=>$base ?? '']]);
                } elseif (! $request->isMethod('GET')) {
                    $base=is_string($request->input('base_airport_code'))?$request->input('base_airport_code'):'';
                    $allowed=Gate::allows($permission,[['airport'=>$base]]);
                }
            }
        }
        if ($action === 'confirm') {
            $allowed = $allowed || Gate::allows('crew.approve');
        }
        if ($section === 'workflows') {
            $allowed = Gate::allows('operations.read') || Gate::allows('crew.confirm', [['self' => $request->user()?->id]]);
        }
        if (! $allowed) {
            AuditService::record('access', $request->user()?->id ?? 'anonymous', 'ACCESS_DENIED', null, ['permission' => $domain.'.'.$action], outcome: 'DENIED');
            abort(403, 'Bạn không có quyền hoặc phạm vi dữ liệu cho thao tác này.');
        }

        return $next($request);
    }
}
