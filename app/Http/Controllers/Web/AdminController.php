<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\Airport;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Services\Core\AircraftService;
use App\Services\Core\AirportService;
use App\Services\Core\CrewService;
use App\Services\Core\FlightManagementService;
use App\Services\Core\RouteService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(
        protected AirportService $airportService,
        protected RouteService $routeService,
        protected AircraftService $aircraftService,
        protected FlightManagementService $flightService,
        protected CrewService $crewService
    ) {}

    /**
     * Bảng điều khiển trung tâm (Dashboard Tổng quan)
     */
    public function dashboard(): View
    {
        $today = Carbon::now('UTC')->toDateString();
        $alerts = $this->aircraftService->getMaintenanceAlerts();

        $stats = [
            'total_airports'   => Airport::count(),
            'total_routes'     => Route::count(),
            'total_aircrafts'  => Aircraft::count(),
            'today_flights'    => OperationalFlight::whereDate('flight_date', $today)->count(),
            'total_crew'       => CrewMember::count(),
            'maintenance_need' => $alerts->count(),
        ];

        $todayFlights = OperationalFlight::with(['route.originAirport', 'route.destAirport', 'aircraft', 'crews'])
            ->whereDate('flight_date', $today)
            ->orderBy('scheduled_dep_utc')
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'todayFlights', 'alerts'));
    }

    // =========================================================================
    // 1. QUẢN LÝ SÂN BAY & TÀI NGUYÊN (UC-01, UC-02)
    // =========================================================================
    public function airports(Request $request): View
    {
        $airports = $this->airportService->page($request->query());
        return view('foundation.airports', compact('airports'));
    }

    public function storeAirport(\App\Http\Requests\Core\StoreAirportRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->airportService->createAirport($validated);

        return redirect()->route('admin.airports')->with('success', "Thêm mới sân bay {$validated['iata_code']} thành công!");
    }

    public function updateAirport(\App\Http\Requests\Core\UpdateAirportRequest $request, string $iataCode): RedirectResponse
    {
        $validated = $request->validated();

        $this->airportService->updateAirport($iataCode, $validated);

        return redirect()->route('admin.airports')->with('success', "Cập nhật sân bay {$iataCode} thành công!");
    }

    public function destroyAirport(string $iataCode): RedirectResponse
    {
        $this->airportService->deleteAirport($iataCode);
        return redirect()->route('admin.airports')->with('success', "Đã xóa sân bay {$iataCode} thành công!");
    }

    public function storeAirportResource(\App\Http\Requests\Core\StoreAirportResourceRequest $request, string $iataCode): RedirectResponse
    {
        $validated = $request->validated();

        $this->airportService->createAirportResource($iataCode, $validated);

        return redirect()->route('admin.airports.show', strtoupper($iataCode))->with('success', "Thêm tài nguyên {$validated['resource_code']} cho sân bay {$iataCode} thành công!");
    }

    public function destroyAirportResource(int $resourceId): RedirectResponse
    {
        $this->airportService->deleteAirportResource($resourceId);
        return redirect()->route('admin.airports')->with('success', 'Đã xóa tài nguyên sân bay thành công!');
    }

    public function airport(Request $request, string $iataCode): View
    {
        $airport = $this->airportService->getAirportByIata($iataCode, false);
        $resources = $this->airportService->pageResources($iataCode,$request->query());
        $history = \Illuminate\Support\Facades\DB::table('audit_logs')->where(fn($q)=>$q
            ->where(fn($a)=>$a->where('entity_type','airports')->where('entity_id',$airport->iata_code))
            ->orWhere(fn($r)=>$r->where('entity_type','airport_resources')->where(fn($v)=>$v
                ->where('before_data->airport_code',$airport->iata_code)->orWhere('after_data->airport_code',$airport->iata_code))))
            ->orderByDesc('id')->paginate(20,['*'],'history_page')->withQueryString();
        return view('foundation.airport-show', compact('airport', 'resources', 'history'));
    }

    public function updateAirportResource(Request $request, int $resourceId): RedirectResponse
    {
        $resource = $this->airportService->updateAirportResource($resourceId, $request->all());
        return redirect()->route('admin.airports.show', $resource->airport_code)->with('success', 'Đã cập nhật tài nguyên.');
    }

    // =========================================================================
    // 2. QUẢN LÝ ĐƯỜNG BAY (UC-04)
    // =========================================================================
    public function routes(Request $request): View
    {
        $routes = $this->routeService->getAllRoutes($request->all());
        $airports = Airport::orderBy('iata_code')->get();
        return view('admin.routes.index', compact('routes', 'airports'));
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'route_code'                => 'nullable|string|max:20',
            'origin_airport_code'       => 'required|string|size:3|exists:airports,iata_code',
            'dest_airport_code'         => 'required|string|size:3|exists:airports,iata_code|different:origin_airport_code',
            'distance_km'               => 'required|numeric|min:1',
            'standard_duration_minutes' => 'required|integer|min:1',
            'status'                    => 'nullable|string|in:ACTIVE,SUSPENDED',
        ]);

        try {
            $route = $this->routeService->createRoute($validated);
            return redirect()->route('admin.routes')->with('success', "Đường bay {$route->route_code} đã được tạo thành công!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.routes')->withErrors($e->errors())->withInput();
        }
    }

    public function destroyRoute(int $id): RedirectResponse
    {
        $this->routeService->deleteRoute($id);
        return redirect()->route('admin.routes')->with('success', 'Đã xóa đường bay thành công!');
    }

    public function updateRoute(\App\Http\Requests\Core\UpdateRouteRequest $request, int $id): RedirectResponse
    {
        $this->routeService->updateRoute($id, $request->validated());
        return redirect()->route('admin.routes')->with('success', 'Đã cập nhật đường bay.');
    }

    public function updateAircraft(\App\Http\Requests\Core\UpdateAircraftRequest $request, string $tailNumber): RedirectResponse
    {
        $this->aircraftService->updateAircraft($tailNumber, $request->validated());
        return redirect()->route('admin.aircrafts')->with('success', 'Đã cập nhật tàu bay.');
    }

    public function updateCrew(\App\Http\Requests\Core\UpdateCrewMemberRequest $request, string $id): RedirectResponse
    {
        $this->crewService->updateCrewMember($id, $request->validated());
        return redirect()->route('admin.crew')->with('success', 'Đã cập nhật hồ sơ tổ bay.');
    }

    public function updateSchedule(Request $request, string $id): RedirectResponse
    {
        app(\App\Services\Core\ScheduleService::class)->save($request->all(), $id);
        return redirect()->route('admin.workflows')->with('success', 'Đã sửa lịch nháp; cần kiểm tra và gửi duyệt lại.');
    }

    // =========================================================================
    // 3. QUẢN LÝ ĐỘI TÀU BAY & DÒNG TÀU BAY (UC-10, UC-11)
    // =========================================================================
    public function aircrafts(Request $request): View
    {
        $aircrafts = $this->aircraftService->getAllAircrafts($request->all());
        $models = $this->aircraftService->getAllModels();
        $alerts = $this->aircraftService->getMaintenanceAlerts();
        return view('admin.aircrafts.index', compact('aircrafts', 'models', 'alerts'));
    }

    public function storeAircraft(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tail_number'         => 'required|string|max:20|unique:aircrafts,tail_number',
            'model_id'            => 'required|integer|exists:aircraft_models,id',
            'manufacture_year'    => 'required|integer|min:1970|max:' . (date('Y') + 1),
            'total_flight_hours'  => 'nullable|numeric|min:0',
            'total_flight_cycles' => 'nullable|integer|min:0',
            'operational_status'  => 'required|string|in:OPERATIONAL,MAINTENANCE,GROUNDED',
        ]);

        $this->aircraftService->createAircraft($validated);

        return redirect()->route('admin.aircrafts')->with('success', "Thêm tàu bay {$validated['tail_number']} thành công!");
    }

    public function storeAircraftModel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'model_code'              => 'required|string|max:30|unique:aircraft_models,model_code',
            'manufacturer'            => 'required|string|max:50',
            'seat_capacity'           => 'required|integer|min:1',
            'turnaround_time_minutes' => 'required|integer|min:15',
        ]);

        $this->aircraftService->createModel($validated);

        return redirect()->route('admin.aircrafts')->with('success', "Thêm dòng máy bay {$validated['model_code']} thành công!");
    }

    public function destroyAircraft(string $tailNumber): RedirectResponse
    {
        $this->aircraftService->deleteAircraft($tailNumber);
        return redirect()->route('admin.aircrafts')->with('success', "Đã xóa tàu bay {$tailNumber} khỏi đội bay!");
    }

    // =========================================================================
    // 4. QUẢN LÝ CHUYẾN BAY & LỊCH BAY (UC-05, UC-06, UC-07, UC-08, UC-09)
    // =========================================================================
    public function flights(Request $request): View
    {
        $flights = $this->flightService->searchFlights($request->all());
        $schedules = $this->flightService->getAllSchedules();
        $routes = Route::with(['originAirport', 'destAirport'])->get();
        $aircrafts = Aircraft::where('operational_status', Aircraft::STATUS_OPERATIONAL)->get();
        $models = AircraftModel::all();
        $crews = CrewMember::where('status', CrewMember::STATUS_AVAILABLE)->get();

        return view('admin.flights.index', compact('flights', 'schedules', 'routes', 'aircrafts', 'models', 'crews'));
    }

    public function storeFlight(\App\Http\Requests\Core\CreateOperationalFlightRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $flight = $this->flightService->createOperationalFlight($validated);
            return redirect()->route('admin.flights')->with('success', "Khởi tạo chuyến bay {$flight->flight_number} ngày {$flight->flight_date} thành công!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.flights')->withErrors($e->errors())->withInput();
        }
    }

    public function storeSchedule(\App\Http\Requests\Core\StoreFlightScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->flightService->createFlightSchedule($validated);

        return redirect()->route('admin.flights')->with('success', "Tạo lịch bay mẫu {$validated['flight_number']} thành công!");
    }

    public function updateFlightStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'flight_status'  => 'required|string|in:SCHEDULED,BOARDING,DEPARTED,ARRIVED,DELAYED,CANCELLED',
            'actual_dep_utc' => 'nullable|date',
            'actual_arr_utc' => 'nullable|date',
            'gate_number'    => 'nullable|string|max:20',
            'delay_reason'   => 'nullable|string|max:500',
        ]);

        $this->flightService->updateFlightStatus($id, $validated);

        return redirect()->route('admin.flights')->with('success', 'Cập nhật trạng thái chuyến bay thành công!');
    }

    // =========================================================================
    // 5. QUẢN LÝ TỔ BAY (UC-15, UC-16, UC-17)
    // =========================================================================
    public function crew(Request $request): View
    {
        $crews = $this->crewService->getAllCrewMembers($request->all());
        $airports = Airport::orderBy('iata_code')->get();
        $activeFlights = OperationalFlight::whereIn('flight_status', [OperationalFlight::STATUS_SCHEDULED, OperationalFlight::STATUS_BOARDING])
            ->orderBy('scheduled_dep_utc')
            ->get();

        $assignments = \App\Models\FlightCrewAssignment::with(['crewMember', 'flight'])
            ->orderBy('duty_start_utc')->get();
        return view('admin.crew.index', compact('crews', 'airports', 'activeFlights', 'assignments'));
    }

    public function storeCrew(\App\Http\Requests\Core\StoreCrewMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->crewService->createCrewMember($validated);

        return redirect()->route('admin.crew')->with('success', "Thêm mới nhân sự {$validated['full_name']} ({$validated['crew_code']}) thành công!");
    }

    public function assignCrew(Request $request, string $flightId): RedirectResponse
    {
        $validated = $request->validate([
            'crew_id'           => 'required|uuid|exists:crew_members,id',
            'assigned_position' => 'required|string|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT',
            'notes'             => 'nullable|string|max:255',
        ]);

        try {
            $this->crewService->assignCrewToFlight($flightId, $validated);
            return redirect()->route('admin.crew')->with('success', 'Phân công nhân sự vào chuyến bay thành công!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.crew')->withErrors($e->errors())->withInput();
        }
    }

    public function destroyCrew(string $id): RedirectResponse
    {
        $this->crewService->deleteCrewMember($id);
        return redirect()->route('admin.crew')->with('success', 'Đã xóa hồ sơ nhân sự tổ bay!');
    }

    public function removeCrewAssignment(string $assignmentId): RedirectResponse
    {
        $this->crewService->removeCrewAssignment($assignmentId);
        return redirect()->back()->with('success', 'Đã hủy phân công nhân sự khỏi chuyến bay!');
    }
}
