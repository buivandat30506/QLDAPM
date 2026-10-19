<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Services\Core\CrewService;
use App\Services\Core\FlightEventService;
use App\Services\Core\MaintenanceService;
use App\Services\Core\MasterDataService;
use App\Services\Core\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        $schedules = FlightSchedule::with('route')->orderByDesc('created_at')->get();
        $flights = OperationalFlight::with('aircraft')->orderByDesc('flight_date')->get();
        $masters = Gate::allows('master.read') ? DB::table('master_data_records')->get() : collect();
        $orders = Gate::allows('maintenance.read') ? DB::table('maintenance_orders')->get() : collect();
        $events = Gate::allows('flight.read') ? DB::table('flight_events')->orderByDesc('id')->limit(30)->get() : collect();
        if (! Gate::allows('operations.read')) { $schedules = collect(); $flights = collect(); }
        $assignments = FlightCrewAssignment::with(['flight', 'crewMember'])
            ->when(! Gate::allows('crew.read'),
                fn ($q) => $q->whereHas('crewMember', fn ($c) => $c->where('user_id', $request->user()->id)))
            ->orderByDesc('created_at')->get();
        $history = DB::table('audit_logs')->when(! Gate::allows('audit.read'), fn ($q) => $q->where('actor_id', $request->user()->id))->orderByDesc('id')->limit(20)->get();
        $crewNotifications=DB::table('crew_notifications')->whereIn('assignment_id',$assignments->pluck('id'))->orderByDesc('id')->limit(100)->get();

        return view('admin.workflows', compact('schedules', 'flights', 'masters', 'orders', 'events', 'assignments', 'history', 'crewNotifications'));
    }

    public function act(Request $request)
    {
        $r = $request->validate(['kind' => 'required|in:schedule,master,maintenance,event,resolve,crew-approve,crew-confirm,crew-replace',
            'id' => 'nullable|string|max:80', 'action' => 'nullable|string|max:30']);
        $permission = match ($r['kind']) {
            'schedule' => in_array($r['action'] ?? '', ['approve', 'publish']) ? 'schedule.approve' : 'schedule.write',
            'event', 'resolve' => 'flight.write',
            'master' => in_array($r['action'] ?? '', ['approve', 'publish', 'reject']) ? 'master.approve' : 'master.write',
            'maintenance' => in_array($r['action'] ?? '', ['approve', 'release']) ? 'maintenance.approve' : 'maintenance.write',
            'crew-confirm' => Gate::allows('crew.approve') ? 'crew.approve' : 'crew.confirm', 'crew-approve' => 'crew.approve', default => 'crew.write',
        };
        Gate::authorize($permission, [['self' => $request->user()->id]]);
        $data = $request->except(['_token', 'kind', 'id']);
        $id = $r['id'] ?? null;
        $action = $r['action'] ?? '';
        if ($r['kind'] === 'master' && $action === 'create') {
            $data['payload'] = ['name' => $request->input('name')];
        }
        $result = match ($r['kind']) {
            'schedule' => app(ScheduleService::class)->transition((string) $id, $action, $data),
            'master' => $action === 'create' ? app(MasterDataService::class)->save($data) : app(MasterDataService::class)->transition((int) $id, $action, $data),
            'maintenance' => $action === 'plan' ? app(MaintenanceService::class)->plan($data) : app(MaintenanceService::class)->act((int) $id, $action, $data),
            'event' => app(FlightEventService::class)->receive($data),
            'resolve' => app(FlightEventService::class)->resolve((string) $id, $data),
            'crew-approve' => app(CrewService::class)->approvePlan((string) $id),
            'crew-confirm' => app(CrewService::class)->confirm((string) $id),
            'crew-replace' => app(CrewService::class)->replace((string) $id, $data),
        };
        if ($r['kind'] === 'event' && $result->status === 'FAILED') {
            return back()->withErrors(['event' => $result->message]);
        }

        return redirect()->route('admin.workflows')->with('success', 'Đã thực hiện thao tác và lưu nhật ký.');
    }
}
