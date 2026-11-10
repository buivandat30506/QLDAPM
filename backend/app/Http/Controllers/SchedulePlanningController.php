<?php

namespace App\Http\Controllers;

use App\Modules\Operations\OperationsService;
use App\Modules\Operations\ScheduleConflicts;
use App\Modules\Operations\SchedulePlanning;
use App\Modules\Shared\Access;
use App\Support\SchedulingDisplay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchedulePlanningController extends Controller
{
    private function choices(Request $r): array
    {
        $db = DB::connection('ops');
        $routes = $db->table('routes as r')->join('airports as o', 'o.code', '=', 'r.origin')->join('airports as d', 'd.code', '=', 'r.destination')->select('r.*', 'o.name as origin_name', 'd.name as destination_name', 'o.timezone as origin_zone', 'd.timezone as destination_zone');
        if (in_array($r->user()->role, Access::SCOPED, true)) {
            $routes->where('r.origin', $r->user()->airport_code);
        }

        return ['routes' => $routes->orderBy('r.origin')->orderBy('r.destination')->get(), 'aircraft' => $db->table('aircraft')->orderBy('registration')->get(['id', 'registration', 'type', 'status']), 'gates' => $db->table('airport_resources')->where('type', 'GATE')->where('active', true)->when(in_array($r->user()->role, Access::SCOPED, true), fn ($q) => $q->where('airport_code', $r->user()->airport_code))->orderBy('airport_code')->orderBy('code')->get(), 'airports' => $db->table('airports')->when(in_array($r->user()->role, Access::SCOPED, true), fn ($q) => $q->where('code', $r->user()->airport_code))->orderBy('code')->get(['code', 'name', 'timezone'])];
    }

    private function query(Request $r)
    {
        $query = DB::connection('ops')->table('flights as f')->join('airports as o', 'o.code', '=', 'f.origin')->join('airports as d', 'd.code', '=', 'f.destination')->join('aircraft as p', 'p.id', '=', 'f.aircraft_id')->select('f.*', 'o.timezone as origin_zone', 'd.timezone as destination_zone', 'o.name as origin_name', 'd.name as destination_name', 'p.registration');
        if (in_array($r->user()->role, Access::SCOPED, true)) {
            $query->where('f.origin', $r->user()->airport_code);
        }

        return $query;
    }

    public function index(Request $r)
    {
        Access::role($r->user(), 'data_admin', 'operations', 'ops_manager');
        $r->validate(['tab' => 'nullable|in:seasons,flights,conflicts,review', 'q' => 'nullable|string|max:100', 'route_id' => 'nullable|integer', 'aircraft_id' => 'nullable|integer', 'airport' => 'nullable|regex:/^[A-Z]{3}$/', 'status' => 'nullable|in:DRAFT,PENDING,SCHEDULED,DELAYED,BOARDING,GATE_CLOSED,DEPARTED,ARRIVED,CANCELLED', 'publication' => 'nullable|in:published,unpublished', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'page' => 'nullable|integer|min:1', 'run' => 'nullable|in:1']);
        $tab = $r->query('tab', 'flights');
        $choices = $this->choices($r);
        $query = $this->query($r);
        if (in_array($tab, ['conflicts', 'review'], true)) {
            Access::role($r->user(), 'operations', 'ops_manager');
        }
        foreach (['route_id', 'aircraft_id', 'status'] as $field) {
            if ($r->filled($field)) {
                $query->where('f.'.$field, $r->input($field));
            }
        }
        if ($r->filled('airport')) {
            $query->where('f.origin', $r->input('airport'));
        }
        if ($r->filled('publication')) {
            $r->input('publication') === 'published' ? $query->whereNotNull('f.published_at') : $query->whereNull('f.published_at');
        }
        if ($r->filled('q')) {
            $query->where('f.number', 'like', '%'.addcslashes($r->input('q'), '\\%_').'%');
        }
        $zone = $choices['airports']->firstWhere('code', $r->input('airport', $r->user()->airport_code))?->timezone ?? 'UTC';
        if ($r->filled('route_id')) {
            $zone = $choices['routes']->firstWhere('id', (int) $r->input('route_id'))?->origin_zone ?? $zone;
        }
        $from = $r->input('from', $tab === 'conflicts' ? now($zone)->toDateString() : null);
        $to = $r->input('to', $tab === 'conflicts' ? now($zone)->addDays(7)->toDateString() : null);
        if ($from) {
            $query->where('f.departure', '>=', Carbon::parse($from, $zone)->startOfDay()->utc());
        }
        if ($to) {
            $query->where('f.departure', '<', Carbon::parse($to, $zone)->addDay()->startOfDay()->utc());
        }
        if ($tab === 'review') {
            $query->whereIn('f.status', ['DRAFT', 'PENDING'])->whereNull('f.published_at');
        }
        $flights = null;
        $plans = null;
        $checked = null;
        if ($tab === 'seasons') {
            $plans = DB::connection('ops')->table('flight_seasons as s')->join('routes as r', 'r.id', '=', 's.route_id')->leftJoin('aircraft as p', 'p.id', '=', 's.aircraft_id')->select('s.*', 'r.origin', 'r.destination', 'p.registration')->selectSub(fn ($q) => $q->from('flight_season_occurrences')->whereColumn('season_id', 's.id')->selectRaw('COUNT(*)'), 'generated_count');
            if (in_array($r->user()->role, Access::SCOPED, true)) {
                $plans->where('r.origin', $r->user()->airport_code);
            }
            if ($r->filled('q')) {
                $plans->where('s.code', 'like', '%'.addcslashes($r->input('q'), '\\%_').'%');
            }
            if ($r->filled('route_id')) {
                $plans->where('s.route_id', $r->input('route_id'));
            }
            $plans = $plans->orderByDesc('s.id')->paginate(12)->withQueryString();
        } elseif ($tab === 'conflicts') {
            if ($r->boolean('run')) {
                $query->whereNotIn('f.status', ['ARRIVED', 'CANCELLED']);
                $total = (clone $query)->count();
                $candidates = $query->orderBy('f.departure')->limit(100)->get();
                $issues = [];
                foreach ($candidates as $f) {
                    foreach (app(ScheduleConflicts::class)->inspect($f, true, $r->user()) as $issue) {
                        $issues[] = $issue + ['flight_number' => $f->number];
                    }
                }
                $checked = ['count' => $candidates->count(), 'total' => $total, 'issues' => $issues];
            }
        } else {
            $flights = $query->orderByDesc('f.departure')->orderBy('f.id')->paginate(12)->withQueryString();
        }
        $data = compact('tab', 'flights', 'plans', 'checked', 'zone', 'from', 'to') + $choices;

        return $r->expectsJson() ? response()->json($data) : view('m02.planning', $data);
    }

    public function planForm(Request $r, ?int $id = null)
    {
        Access::role($r->user(), 'data_admin', 'operations', 'ops_manager');
        $plan = $id ? app(SchedulePlanning::class)->plan($r->user(), $id) : null;

        return view('m02.plan-form', compact('plan') + $this->choices($r));
    }

    public function savePlan(Request $r, ?int $id = null)
    {
        $result = app(SchedulePlanning::class)->save($r->user(), $r->all(), $id);

        return $r->expectsJson() ? response()->json($result, $id ? 200 : 201) : redirect('/workspace/planning/plans/'.$result->id)->with('message', 'Đã lưu kế hoạch định kỳ. Chưa công bố chuyến bay.');
    }

    public function planShow(Request $r, int $id)
    {
        $plan = app(SchedulePlanning::class)->plan($r->user(), $id);
        $db = DB::connection('ops');
        $route = $db->table('routes')->find($plan->route_id);
        $zones = $db->table('airports')->whereIn('code', [$route->origin, $route->destination])->pluck('timezone', 'code');
        $occurrences = $db->table('flight_season_occurrences as x')->join('flights as f', 'f.id', '=', 'x.flight_id')->where('x.season_id', $id)->select('x.*', 'f.number', 'f.departure', 'f.arrival', 'f.status', 'f.published_at')->orderByDesc('x.local_date')->paginate(12)->withQueryString();
        $audit = $db->table('audit_logs')->where('object_id', (string) $id)->whereIn('action', ['season.created', 'season.updated', 'season.generated'])->latest('id')->limit(10)->get();

        return $r->expectsJson() ? response()->json(compact('plan', 'route', 'zones', 'occurrences', 'audit')) : view('m02.plan-detail', compact('plan', 'route', 'zones', 'occurrences', 'audit'));
    }

    public function preview(Request $r, int $id)
    {
        $data = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $preview = app(SchedulePlanning::class)->preview($r->user(), $id, $data['from'], $data['to']);

        return $r->expectsJson() ? response()->json($preview) : view('m02.generation-preview', $preview);
    }

    public function generate(Request $r, int $id)
    {
        $data = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'version' => 'required|integer']);
        $result = app(SchedulePlanning::class)->generate($r->user(), $id, $data['from'], $data['to'], $data['version']);

        return $r->expectsJson() ? response()->json($result) : redirect('/workspace/planning/plans/'.$id)->with('message', 'Đã tạo '.count($result['created']).' chuyến nháp; bỏ qua '.count($result['skipped']).' chuyến đã phát sinh.');
    }

    public function showFlight(Request $r, int $id)
    {
        app(SchedulePlanning::class)->flight($r->user(), $id);
        $db = DB::connection('ops');
        $flight = $this->query($r)->where('f.id', $id)->first() ?? abort(404);
        $source = $db->table('flight_season_occurrences as x')->join('flight_seasons as s', 's.id', '=', 'x.season_id')->where('x.flight_id', $id)->select('x.*', 's.code')->first();
        $changes = $db->table('flight_schedule_changes')->where('flight_id', $id)->latest('id')->get();
        $history = $db->table('audit_logs')->where('object_id', (string) $id)->whereIn('action', ['schedule.created', 'schedule.submitted', 'schedule.published', 'schedule.edit.proposed', 'schedule.edit.approved'])->latest('id')->paginate(5)->withQueryString();
        $actors = DB::table('users')->whereIn('id', $history->pluck('actor_id')->merge($changes->pluck('created_by'))->merge($changes->pluck('approved_by'))->push($flight->created_by)->push($flight->approved_by)->filter()->unique())->pluck('name', 'id');

        return $r->expectsJson() ? response()->json(compact('flight', 'source', 'changes', 'history')) : view('m02.flight-detail', compact('flight', 'source', 'changes', 'history', 'actors') + $this->choices($r));
    }

    public function newFlight(Request $r)
    {
        Access::role($r->user(), 'data_admin', 'operations');

        return view('m02.flight-form', $this->choices($r));
    }

    public function createFlight(Request $r)
    {
        Access::role($r->user(), 'data_admin', 'operations');
        $data = $r->validate(['route_id' => 'required|integer', 'departure' => 'required|date_format:Y-m-d\TH:i', 'arrival' => 'required|date_format:Y-m-d\TH:i']);
        $route = DB::connection('ops')->table('routes')->find($data['route_id']) ?? abort(422);
        Access::airport($r->user(), $route->origin);
        abort_unless(DB::connection('ops')->table('airport_resources')->where('airport_code', $route->origin)->where('code', $r->input('gate'))->where('type', 'GATE')->where('active', true)->exists(), 422, 'Cổng khởi hành phải đang hoạt động và thuộc sân bay đi.');
        $zones = DB::connection('ops')->table('airports')->whereIn('code', [$route->origin, $route->destination])->pluck('timezone', 'code');
        $input = $r->all();
        foreach (['departure' => $route->origin, 'arrival' => $route->destination] as $field => $airport) {
            [$date,$time] = explode('T', $data[$field]);
            $input[$field] = SchedulingDisplay::local($date, $time, $zones[$airport])->toIso8601String();
        }
        $result = app(OperationsService::class)->execute($r->user(), 'schedule', $input);

        return $r->expectsJson() ? response()->json($result, 201) : redirect('/workspace/planning/flights/'.$result->id)->with('message', 'Đã tạo lịch nháp. Chưa công bố.');
    }

    public function batch(Request $r)
    {
        $data = $r->validate(['action' => 'required|in:submit,publish', 'flight_ids' => 'required|array|min:1|max:31', 'flight_ids.*' => 'required|integer|distinct', 'apply' => 'nullable|boolean']);
        $result = app(SchedulePlanning::class)->batch($r->user(), $data['action'], $data['flight_ids'], (bool) ($data['apply'] ?? false));
        $payload = $result + ['action' => $data['action'], 'ids' => $data['flight_ids']];

        return $r->expectsJson() ? response()->json($payload) : view('m02.batch-review',$payload);
    }

    public function proposeEdit(Request $r,int $id)
    {
        $change = app(SchedulePlanning::class)->proposeEdit($r->user(),$id,$r->all());

        return $r->expectsJson() ? response()->json(['id' => $change],201) : back()->with('message','Đã gửi đề nghị sửa lịch; cần người duyệt độc lập.');
    }

    public function approveEdit(Request $r,int $id)
    {
        app(SchedulePlanning::class)->approveEdit($r->user(),$id);

        return $r->expectsJson() ? response()->json(['id' => $id]) : back()->with('message','Đã duyệt sửa lịch. Chuyến trở về nháp và cần gửi phê duyệt lại trước khi công bố.');
    }
}
