<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\AssignCrewFlightRequest;
use App\Http\Requests\Core\StoreCrewMemberRequest;
use App\Http\Requests\Core\UpdateCrewMemberRequest;
use App\Models\AircraftModel;
use App\Models\Airport;
use App\Models\FlightCrewAssignment;
use App\Models\OperationalFlight;
use App\Services\Core\CrewAccess;
use App\Services\Core\CrewCalendar;
use App\Services\Core\CrewRulesService;
use App\Services\Core\CrewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrewWorkflowController extends Controller
{
    public function __construct(private CrewService $service) {}

    public function index(Request $r)
    {
        $crew = $this->service->query($r->query())->paginate(20)->withQueryString();

        return view('crew.index', ['crew' => $crew] + $this->lookups());
    }

    private function lookups(): array
    {
        return ['models' => AircraftModel::orderBy('model_code')->get(), 'airports' => Airport::orderBy('iata_code')->get()];
    }

    public function show(string $id)
    {
        $member = $this->service->getCrewMemberById($id);
        $history = DB::table('audit_logs')->where('entity_type', 'crew_members')->where('entity_id', $id)->orderByDesc('id')->limit(30)->get();
        $actors = DB::table('users')->whereIn('id', $history->pluck('actor_id')->filter()->unique())->pluck('username', 'id');

        return view('crew.show', ['member' => $member, 'windows' => DB::table('crew_availability_windows')->where('crew_id', $id)->orderByDesc('id')->paginate(15),
            'history' => $history, 'actors' => $actors] + $this->lookups());
    }

    public function store(StoreCrewMemberRequest $r)
    {
        $m = $this->service->createCrewMember($r->validated());

        return to_route('admin.crew.show', $m->id)->with('success', 'Đã tạo hồ sơ tổ bay.');
    }

    public function update(UpdateCrewMemberRequest $r, string $id)
    {
        $this->service->updateCrewMember($id, $r->validated());

        return to_route('admin.crew.show', $id)->with('success', 'Đã cập nhật hồ sơ; phân công bị ảnh hưởng cần kiểm tra lại.');
    }

    public function calendar(Request $r, string $id)
    {
        app(CrewCalendar::class)->add($id, $r->all());

        return to_route('admin.crew.show', $id)->with('success', 'Đã ghi lịch khả dụng và kiểm tra phân công liên quan.');
    }

    public function cancelCalendar(Request $r, string $id, int $window)
    {
        app(CrewCalendar::class)->cancel($id, $window, $r->all());

        return to_route('admin.crew.show', $id)->with('success', 'Đã hủy khoảng khả dụng; lịch sử được giữ.');
    }

    public function plans(Request $r)
    {
        $q = CrewAccess::flights(OperationalFlight::with(['route', 'crewAssignments']));
        if (is_string($r->query('search'))) {
            $q->where('flight_number', 'like', '%'.substr($r->query('search'), 0, 30).'%');
        }

        return view('crew.plans', ['flights' => $q->orderByDesc('scheduled_dep_utc')->paginate(20)->withQueryString()]);
    }

    public function plan(Request $r, string $flightId)
    {
        $flight = OperationalFlight::with('route')->findOrFail($flightId);
        CrewAccess::flight('read', $flight);
        $data = $r->validate(['position' => 'nullable|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT', 'replace_id' => 'nullable|uuid']);
        $suggestions = $this->service->suggestions($flightId, $data['position'] ?? 'CAPTAIN', $data['replace_id'] ?? null);

        $assignments = $this->service->getFlightCrewAssignments($flightId);
        $events = DB::table('crew_assignment_events as e')->join('flight_crew_assignments as a', 'e.assignment_id', '=', 'a.id')->where('a.flight_id', $flightId)->select('e.*')->orderByDesc('e.id')->paginate(20);
        $actors = DB::table('users')->whereIn('id', $events->pluck('actor_id')->merge($assignments->pluck('approved_by'))->merge($assignments->pluck('confirmed_by'))->filter()->unique())->pluck('username', 'id');

        return view('crew.plan', ['flight' => $flight, 'assignments' => $assignments, 'suggestions' => $suggestions,
            'events' => $events, 'actors' => $actors,
            'policy' => app(CrewRulesService::class)->policy()]);
    }

    public function assign(AssignCrewFlightRequest $r, string $flightId)
    {
        $this->service->assignCrewToFlight($flightId, $r->validated());

        return to_route('admin.crew.plan', $flightId)->with('success', 'Đã lưu phân công; cần duyệt và ghi nhận xác nhận.');
    }

    public function approve(string $flightId)
    {
        $this->service->approvePlan($flightId);

        return to_route('admin.crew.plan', $flightId)->with('success', 'Đã duyệt và gửi thông báo nội bộ tới thành viên.');
    }

    public function action(Request $r, string $id, string $action)
    {
        $a = FlightCrewAssignment::findOrFail($id);
        match ($action) {
            'confirm' => $this->service->confirm($id),'replace' => $this->service->replace($id, $r->all()),'remove' => $this->service->removeCrewAssignment($id)
        };

        return to_route('admin.crew.plan', $a->flight_id)->with('success','Đã ghi nhận thao tác và lưu lịch sử phân công.');
    }
}
