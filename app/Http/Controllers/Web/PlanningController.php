<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\Airport;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Services\Core\PlanningAccess;
use App\Services\Core\RouteService;
use App\Services\Core\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanningController extends Controller
{
    public function routes(Request $request, RouteService $service)
    {
        return view('planning.routes', ['routes' => $service->query($request->query())->paginate((int) $request->input('per_page', 15))->withQueryString(),
            'airports' => Airport::orderBy('iata_code')->get(), 'models' => AircraftModel::orderBy('model_code')->get()]);
    }

    public function route(int $id, RouteService $service)
    {
        $route = $service->getRouteById($id);
        return view('planning.route-show', ['route' => $route, 'airports' => Airport::orderBy('iata_code')->get(),
            'models' => AircraftModel::orderBy('model_code')->get(), 'history' => DB::table('audit_logs')->where('entity_type', 'routes')->where('entity_id', (string) $id)->orderByDesc('id')->paginate(15)->withQueryString()]);
    }

    public function saveRoute(Request $request, RouteService $service, ?int $id = null)
    {
        $data = $request->all();
        foreach (['allowed_model_ids', 'blocked_weekdays'] as $field) { $data[$field] = $request->input($field, []); }
        $id ? $service->updateRoute($id, $data) : $service->createRoute($data);
        return redirect()->route('admin.routes')->with('success', 'Đã lưu đường bay, mùa khai thác và hạn chế.');
    }

    public function schedules(Request $request, ScheduleService $service)
    {
        return view('planning.schedules', ['schedules' => $service->query($request->query())->paginate((int) $request->input('per_page', 15))->withQueryString(),
            'options' => $this->options()]);
    }

    public function schedule(string $id, ScheduleService $service)
    {
        $schedule = $service->find($id);
        $family = FlightSchedule::where('series_id', $schedule->series_id ?: $schedule->id)->orWhere('id', $id)->pluck('id');
        $actorIds = DB::table('audit_logs')->where('entity_type', 'flight_schedules')->whereIn('entity_id', $family)->whereNotNull('actor_id')->pluck('actor_id');
        return view('planning.schedule-show', ['schedule' => $schedule, 'options' => $this->options(), 'impacts' => $service->impacts($schedule),
            'checks' => DB::table('flight_schedule_checks')->where('schedule_id', $id)->orderByDesc('id')->paginate(10, ['*'], 'checks_page')->withQueryString(),
            'versions' => DB::table('entity_versions')->where('entity_type', 'flight_schedules')->whereIn('entity_id', $family)->orderByDesc('version')->get(),
            'family' => FlightSchedule::whereIn('id', $family)->orderBy('created_at')->get(),
            'actors' => \App\Models\User::whereIn('id', $actorIds->merge([$schedule->submitted_by,$schedule->approved_by,$schedule->published_by]))->get(['id','full_name'])->keyBy('id'),
            'history' => DB::table('audit_logs')->where('entity_type', 'flight_schedules')->whereIn('entity_id', $family)->orderByDesc('id')->paginate(15, ['*'], 'history_page')->withQueryString()]);
    }

    public function saveSchedule(Request $request, ScheduleService $service, ?string $id = null)
    {
        $schedule = $service->save($request->all(), $id);
        return redirect()->route('admin.schedules.show', $schedule->id)->with('success', 'Đã lưu lịch nháp. Kiểm tra xung đột trước khi gửi duyệt.');
    }

    public function action(Request $request, string $id, string $action, ScheduleService $service)
    {
        if ($action === 'check') {
            $report = $service->inspect($id);
            return redirect()->route('admin.schedules.show', $id)->with('success', $report['is_valid'] ? 'Kiểm tra hợp lệ; chưa thay đổi trạng thái lịch.' : 'Đã ghi nhận các xung đột. Xem báo cáo bên dưới.');
        }
        $schedule = $service->transition($id, $action, $request->all());
        return redirect()->route('admin.schedules.show', $schedule->id)->with('success', 'Đã cập nhật lịch và lưu lịch sử.');
    }

    public function apiSchedule(string $id, ScheduleService $service)
    {
        $schedule = $service->find($id);
        return response()->json(['success' => true, 'data' => $schedule, 'impacts' => $service->impacts($schedule)]);
    }

    public function apiCheck(string $id, ScheduleService $service) { return response()->json(['success' => true, 'data' => $service->inspect($id)]); }

    private function options(): array
    {
        return ['routes' => app(RouteService::class)->getAllRoutes(), 'models' => AircraftModel::orderBy('model_code')->get(),
            'aircraft' => Aircraft::with('model')->orderBy('tail_number')->get(), 'gates' => AirportResource::where('resource_type', 'GATE')->orderBy('airport_code')->orderBy('resource_code')->get()];
    }
}
