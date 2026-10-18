<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\User;
use App\Services\Core\MaintenanceAccess;
use App\Services\Core\MaintenanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceWorkflow $service) {}

    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:50', 'status' => 'nullable|in:ACTIVE,RESOLVED']);
        $aircrafts = MaintenanceAccess::filter(Aircraft::query(), 'base_airport_code')->orderBy('tail_number')->get();
        $stock = MaintenanceAccess::filter(DB::table('maintenance_materials'), 'airport_code')->orderBy('code')->paginate(15, ['*'], 'stock_page')->withQueryString();
        return $r->is('api/*') ? response()->json(['data' => $this->service->plans($filters)])
            : view('maintenance.index', ['plans' => $this->service->plans($filters), 'aircrafts' => $aircrafts, 'stock' => $stock,
                'legacyLocations' => AirportResource::whereIn('airport_code', $aircrafts->pluck('base_airport_code')->filter()->unique())->where('resource_type', 'STAND')->where('status', 'AVAILABLE')->get(),
                'legacy' => MaintenanceAccess::filter(DB::table('maintenance_orders as o')->join('aircrafts as a', 'a.tail_number', '=', 'o.aircraft_tail'))->whereNull('o.plan_id')->where('o.status', '!=', 'RELEASED')->select('o.*', 'a.base_airport_code')->paginate(10, ['*'], 'legacy_page')->withQueryString()]);
    }

    public function show(Request $r, int $plan)
    {
        $d = $this->service->detail($plan);
        if ($r->is('api/*')) { return response()->json(['data' => $d]); }
        $base = $d['aircraft']->base_airport_code;
        $d['locations'] = AirportResource::where('airport_code', $base)->where('resource_type', 'STAND')->where('status', 'AVAILABLE')->get();
        $d['technicians'] = User::where('status', 'ACTIVE')->orderBy('username')->get(['id', 'username', 'status'])->filter(fn ($u) => Gate::forUser($u)->allows('maintenance.write', $base ? [['airport' => $base]] : []));
        $d['stock'] = DB::table('maintenance_materials')->where('airport_code', $base)->orderBy('code')->get();
        return view('maintenance.show', $d);
    }

    public function save(Request $r, ?int $plan = null)
    {
        $data = $r->all();
        if (is_string($r->input('task_lines'))) { $data['tasks'] = preg_split('/\r\n|\r|\n/', trim($r->input('task_lines'))); }
        $p = $this->service->savePlan($data, $plan);
        return $r->is('api/*') ? response()->json(['data' => $p], $plan ? 200 : 201) : to_route('admin.maintenance.show', $p->id)->with('success', 'Đã lưu kế hoạch và lịch sử thay đổi.');
    }

    public function order(Request $r, int $plan)
    {
        $data = $r->all();
        if (! $r->is('api/*')) { $data['materials'] = array_values(array_filter($data['materials'] ?? [], fn ($m) => (int) ($m['quantity'] ?? 0) > 0)); }
        $o = $this->service->createOrder($plan, $data);
        return $r->is('api/*') ? response()->json(['data' => $o], 201) : to_route('admin.maintenance.show', $plan)->with('success', 'Đã tạo lệnh bảo dưỡng.');
    }

    public function act(Request $r, int $order, string $action)
    {
        $data = $r->all();
        if (! $r->is('api/*') && $action === 'assign') { $data['materials'] = array_values(array_filter($data['materials'] ?? [], fn ($m) => (int) ($m['quantity'] ?? 0) > 0)); }
        $o = $this->service->action($order, $action, $data);
        return $r->is('api/*') ? response()->json(['data' => $o]) : to_route('admin.maintenance.show', $o->plan_id)->with('success', $o->status === 'WAITING_MATERIALS' ? 'Thiếu vật tư: lệnh đang chờ bổ sung, tàu bay không khả dụng.' : 'Đã ghi nhận thao tác và người chịu trách nhiệm.');
    }

    public function upload(Request $r, int $order)
    {
        $r->validate(['file' => 'required|file']); $e = $this->service->upload($order, $r->file('file'));
        return $r->is('api/*') ? response()->json(['data' => $e], 201) : back()->with('success', 'Đã lưu tài liệu riêng tư và SHA256.');
    }

    public function download(int $evidence) { return $this->service->download($evidence); }

    public function adopt(Request $r, int $order)
    {
        $o = $this->service->adopt($order, $r->all());
        return $r->is('api/*') ? response()->json(['data' => $o]) : to_route('admin.maintenance.show', $o->plan_id)->with('success', 'Đã chuyển hồ sơ; cần kiểm tra công việc, vật tư và ký kỹ thuật theo quy trình đầy đủ.');
    }

    public function stock(Request $r)
    {
        $m = $this->service->receiveMaterial($r->all());
        return $r->is('api/*') ? response()->json(['data' => $m]) : to_route('admin.maintenance')->with('success', 'Đã ghi nhận phiếu nhập kho.');
    }
}
