<?php

namespace App\Http\Controllers;

use App\Modules\Shared\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CoreDependenciesController extends Controller
{
    public function crew(Request $r)
    {
        Access::role($r->user(), 'crew_scheduler');
        $r->validate(['flight_id' => 'nullable|integer|min:1']);
        $db = DB::connection('ops');
        $flights = $db->table('flights')->whereIn('status', ['DRAFT', 'PENDING', 'SCHEDULED', 'DELAYED'])->when($r->filled('flight_id'), fn ($q) => $q->where('id', $r->integer('flight_id')))->orderByDesc('id')->limit(100)->get();
        $crew = $db->table('crew_members')->where('available', true)->where('license_expires', '>=', now()->toDateString())->orderBy('name')->get();
        $assignments = $db->table('crew_assignments as a')->join('crew_members as c', 'c.id', '=', 'a.crew_member_id')->whereIn('a.flight_id', $flights->pluck('id'))->where('a.active', true)->get(['a.flight_id', 'c.code', 'c.name', 'c.position']);

        return view('core-dependencies', compact('flights', 'crew', 'assignments') + ['mode' => 'crew', 'title' => 'Phân công tổ bay cho lịch chuyến']);
    }

    public function ground(Request $r)
    {
        Access::role($r->user(), 'ground_agent');
        $r->validate(['flight_id' => 'nullable|integer|min:1']);
        $db = DB::connection('ops');
        $tasks = $db->table('ground_tasks as t')->join('flights as f', 'f.id', '=', 't.flight_id')->when(! Access::isAdmin($r->user()), fn ($q) => $q->where('t.assigned_to', $r->user()->id)->where('f.origin', $r->user()->airport_code))->when($r->filled('flight_id'), fn ($q) => $q->where('f.id', $r->integer('flight_id')))->select('t.*', 'f.number', 'f.origin', 'f.destination')->orderByDesc('t.id')->paginate(12)->withQueryString();

        return view('core-dependencies',compact('tasks') + ['mode' => 'ground', 'title' => 'Nhiệm vụ phục vụ chuyến bay']);
    }
}
