<?php

namespace App\Http\Controllers;

use App\Modules\Operations\MasterDataService;
use App\Modules\Operations\OperationsService;
use App\Modules\Shared\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteManagementController extends Controller
{
    private function query()
    {
        return DB::connection('ops')->table('routes as r')->join('airports as o', 'o.code', '=', 'r.origin')->join('airports as d', 'd.code', '=', 'r.destination')->select('r.*', 'o.name as origin_name', 'd.name as destination_name');
    }

    public function index(Request $request)
    {
        Access::role($request->user(), 'data_admin');
        $request->validate(['q' => 'nullable|string|max:100', 'active' => 'nullable|in:0,1', 'origin' => 'nullable|regex:/^[A-Z]{3}$/', 'destination' => 'nullable|regex:/^[A-Z]{3}$/', 'page' => 'nullable|integer|min:1', 'pending_page' => 'nullable|integer|min:1']);
        $query = $this->query();
        foreach (['origin', 'destination', 'active'] as $field) {
            if ($request->filled($field)) {
                $query->where('r.'.$field, $request->input($field));
            }
        }
        if ($request->filled('q')) {
            $term = trim($request->input('q'));
            if (preg_match('/^#?R?(\d+)$/i', $term, $m)) {
                $query->where('r.id', (int) $m[1]);
            } else {
                foreach (preg_split('/[\s→\-]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $like = '%'.addcslashes($part, '\\%_').'%';
                    $query->where(fn ($q) => $q->where('r.origin', 'like', $like)->orWhere('r.destination', 'like', $like)->orWhere('o.name', 'like', $like)->orWhere('d.name', 'like', $like));
                }
            }
        }
        $routes = $query->orderBy('r.origin')->orderBy('r.destination')->paginate(12)->withQueryString();
        $pending = DB::connection('ops')->table('master_changes as c')->join('routes as r', 'r.id', '=', 'c.record_id')->where('c.entity', 'routes')->where('c.status', 'PENDING')->select('c.*', 'r.origin', 'r.destination')->latest('c.id')->paginate(5, ['*'], 'pending_page')->withQueryString();
        $airports = DB::connection('ops')->table('airports')->orderBy('code')->get(['code', 'name']);

        return $request->expectsJson() ? response()->json(compact('routes', 'pending', 'airports')) : view('m02.routes', compact('routes', 'pending', 'airports'));
    }

    public function create(Request $request)
    {
        Access::role($request->user(), 'data_admin');

        return view('m02.route-form', ['airports' => DB::connection('ops')->table('airports')->orderBy('code')->get()]);
    }

    public function store(Request $request, OperationsService $service)
    {
        $result = $service->execute($request->user(), 'route', $request->all());

        return $request->expectsJson() ? response()->json($result, 201) : redirect('/workspace/catalog/routes/'.$result->id)->with('message', 'Đã tạo đường bay.');
    }

    public function show(Request $request, int $id)
    {
        Access::role($request->user(), 'data_admin');
        $db = DB::connection('ops');
        $route = $this->query()->where('r.id', $id)->first() ?? abort(404);
        $changes = $db->table('master_changes')->where('entity', 'routes')->where('record_id', (string) $id)->latest('id')->paginate(5)->withQueryString();
        $plans = $db->table('flight_seasons')->where('route_id', $id)->orderByDesc('id')->limit(5)->get();
        $actors = DB::table('users')->whereIn('id', $changes->pluck('created_by')->merge($changes->pluck('approved_by'))->filter()->unique())->pluck('name', 'id');

        return $request->expectsJson() ? response()->json(compact('route', 'changes', 'plans')) : view('m02.route-detail', compact('route', 'changes', 'plans', 'actors'));
    }

    public function propose(Request $request, int $id, MasterDataService $service)
    {
        Access::role($request->user(), 'data_admin');
        $current = DB::connection('ops')->table('routes')->find($id) ?? abort(404);
        $data = $request->validate(['duration_minutes' => 'required|integer|between:15,1440', 'distance_km' => 'required|integer|between:1,30000', 'active' => 'required|boolean', 'reason' => 'required|string|min:5|max:255']);
        $reason = $data['reason'];
        unset($data['reason']);
        abort_if(! array_diff_assoc($data, (array) $current), 422, 'Chưa có nội dung thay đổi.');
        $result = $service->propose($request->user(), ['entity' => 'routes', 'record_id' => (string) $id, 'changes' => $data, 'reason' => $reason]);

        return $request->expectsJson() ? response()->json($result, 201) : back()->with('message', 'Đã gửi thay đổi; cần người khác phê duyệt.');
    }

    public function approve(Request $request, int $id, int $change, MasterDataService $service)
    {
        Access::role($request->user(), 'data_admin');
        abort_unless(DB::connection('ops')->table('master_changes')->where('id', $change)->where('entity','routes')->where('record_id',(string) $id)->exists(), 404);
        $result = $service->approve($request->user(),$change);

        return $request->expectsJson() ? response()->json($result) : back()->with('message','Đã phê duyệt thay đổi đường bay.');
    }
}
