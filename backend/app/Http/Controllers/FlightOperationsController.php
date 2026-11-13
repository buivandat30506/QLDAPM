<?php

namespace App\Http\Controllers;

use App\Modules\Shared\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlightOperationsController extends Controller
{
    private function query(Request $r)
    {
        Access::role($r->user(), 'operations', 'ops_manager');

        return DB::connection('ops')->table('flights as f')->join('airports as o', 'o.code', '=', 'f.origin')->join('airports as d', 'd.code', '=', 'f.destination')->join('aircraft as a', 'a.id', '=', 'f.aircraft_id')->whereNotIn('f.status', ['DRAFT', 'PENDING'])->select('f.*', 'o.timezone as origin_zone', 'd.timezone as destination_zone', 'a.registration')->when(in_array($r->user()->role, Access::SCOPED, true), fn ($q) => $q->where('f.origin', $r->user()->airport_code));
    }

    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'flight_id' => 'nullable|integer', 'flight_ids' => 'nullable|array|max:31', 'flight_ids.*' => 'integer|min:1', 'page' => 'nullable|integer|min:1']);
        $query = $this->query($r);
        if ($r->filled('q')) {
            $query->where('f.number', 'like', '%'.addcslashes($r->input('q'), '\\%_').'%');
        }
        if ($r->filled('flight_id')) {
            $query->where('f.id', $r->input('flight_id'));
        }
        if ($r->filled('flight_ids')) {
            $query->whereIn('f.id', $r->input('flight_ids'));
        }
        $flights = $query->orderByDesc('f.departure')->paginate(12)->withQueryString();

        return $r->expectsJson() ? response()->json($flights) : view('m02.operations', compact('flights'));
    }

    public function show(Request $r, int $id)
    {
        $flight = $this->query($r)->where('f.id', $id)->first() ?? abort(404);
        $db = DB::connection('ops');
        $crew = $db->table('crew_assignments as x')->join('crew_members as c', 'c.id', '=', 'x.crew_member_id')->where('x.flight_id', $id)->where('x.active', true)->get(['c.code', 'c.name', 'c.position', 'c.qualification']);
        $allocations = $db->table('resource_allocations as x')->join('airport_resources as r', 'r.id', '=', 'x.resource_id')->where('x.flight_id', $id)->get(['r.code', 'r.type', 'x.starts_at', 'x.ends_at']);
        $history = $db->table('audit_logs')->where('object_id', (string) $id)->where(fn ($q) => $q->where('action', 'like', 'flight.%')->orWhere('action', 'schedule.published'))->latest('id')->limit(50)->get();
        $actualEvents = $db->table('flight_actual_events')->where('flight_id', $id)->orderByDesc('id')->get();
        $next = ['SCHEDULED' => 'BOARDING', 'DELAYED' => 'BOARDING', 'BOARDING' => 'GATE_CLOSED', 'GATE_CLOSED' => 'DEPARTED', 'DEPARTED' => 'ARRIVED'][$flight->status] ?? null;

        return $r->expectsJson() ? response()->json(compact('flight', 'crew', 'allocations', 'history', 'actualEvents')) : view('m02.operation-detail', compact('flight', 'crew', 'allocations', 'history', 'actualEvents', 'next'));
    }
}
