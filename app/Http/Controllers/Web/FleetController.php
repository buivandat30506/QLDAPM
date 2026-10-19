<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\AircraftConfigurationRequest;
use App\Http\Requests\Core\StoreAircraftModelRequest;
use App\Http\Requests\Core\StoreAircraftRequest;
use App\Http\Requests\Core\UpdateAircraftRequest;
use App\Models\Airport;
use App\Services\Core\AircraftService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FleetController extends Controller
{
    public function __construct(private AircraftService $service) {}

    public function index(Request $request)
    {
        return view('fleet.index', ['aircrafts' => $this->service->page($request->query()), 'models' => $this->service->getAllModels(),
            'airports' => Airport::orderBy('iata_code')->get()]);
    }

    public function show(Request $request, string $tailNumber)
    {
        $a = $this->service->getAircraftByTail($tailNumber);
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'source' => 'nullable|in:legacy,OPERATIONS,AODB,FIDS,DEMO']);
        $usage = DB::table('aircraft_usage_records as u')->join('operational_flights as f', 'u.flight_id', '=', 'f.id')
            ->where('u.aircraft_tail', $a->tail_number)->select('u.*', 'f.flight_number');
        if (! empty($filters['from'])) {
            $usage->where('u.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $usage->where('u.created_at', '<', Carbon::parse($filters['to'], 'UTC')->addDay());
        }
        if (! empty($filters['source'])) {
            $usage->where('u.source', $filters['source']);
        }

        $configurations = $a->configurations()->orderByDesc('version')->paginate(10, ['*'], 'config_page')->withQueryString();
        $history = DB::table('audit_logs')->where('entity_type', 'aircrafts')->where('entity_id', $a->tail_number)->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString();
        $actors = DB::table('users')->whereIn('id', $configurations->pluck('created_by')->merge($history->pluck('actor_id'))->filter()->unique())->pluck('username', 'id');

        return view('fleet.show', ['aircraft' => $a, 'models' => $this->service->getAllModels(), 'airports' => Airport::orderBy('iata_code')->get(),
            'usage' => $usage->orderByDesc('u.id')->paginate(20, ['*'], 'usage_page')->withQueryString(),
            'configurations' => $configurations, 'actors' => $actors,
            'reconciliation' => $this->service->reconciliation($a),
            'alerts' => DB::table('aircraft_usage_alerts')->where('aircraft_tail', $a->tail_number)->orderByDesc('id')->paginate(10, ['*'], 'alert_page')->withQueryString(),
            'history' => $history]);
    }

    public function store(StoreAircraftRequest $request)
    {
        $a = $this->service->createAircraft($request->validated());

        return to_route('admin.aircrafts.show', $a->tail_number)->with('success', 'Đã thêm tàu bay. Hãy xác nhận cấu hình ghế trước khi mở check-in.');
    }

    public function update(UpdateAircraftRequest $request, string $tailNumber)
    {
        $this->service->updateAircraft($tailNumber, $request->validated());

        return to_route('admin.aircrafts.show', $tailNumber)->with('success', 'Đã cập nhật hồ sơ tàu bay.');
    }

    public function configure(AircraftConfigurationRequest $request, string $tailNumber)
    {
        $this->service->configure($tailNumber, $request->validated());

        return to_route('admin.aircrafts.show', $tailNumber)->with('success', 'Đã công bố phiên bản cấu hình ghế.');
    }

    public function storeModel(StoreAircraftModelRequest $request)
    {
        $this->service->createModel($request->validated());

        return to_route('admin.aircrafts')->with('success', 'Đã thêm dòng tàu bay.');
    }
}
