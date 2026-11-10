<?php

namespace App\Http\Controllers;

use App\Modules\Operations\InfrastructureRepository;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InfrastructureReferenceController extends Controller
{
    private function authorizePurpose(Request $request, string $purpose): void
    {
        $roles = ['baggage' => ['airport_agent', 'data_admin'], 'ground' => ['operations', 'ground_agent', 'data_admin'], 'flight' => ['operations', 'ops_manager', 'data_admin']][$purpose] ?? abort(404);
        Access::role($request->user(), ...$roles);
    }

    public function index(Request $request, string $purpose, InfrastructureRepository $repository)
    {
        $this->authorizePurpose($request, $purpose);
        $request->validate(['airport' => 'nullable|regex:/^[A-Z]{3}$/', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'equipment_id' => 'nullable|integer|min:1']);
        $user = $request->user();
        $db = DB::connection('ops');
        $choices = $db->table('airports');
        if (! Access::isAdmin($user) && in_array($user->role, Access::SCOPED, true)) {
            $choices->where('code', $user->airport_code);
        }
        $choices = $choices->orderBy('name')->get(['code', 'name']);
        $selected = $request->query('airport', in_array($user->role, Access::SCOPED, true) ? $user->airport_code : null);
        $airport = $selected ? ($choices->firstWhere('code', $selected) ?? abort(404)) : null;
        $items = null;
        $equipment = collect();
        if ($airport) {
            $query = $repository->listing($airport->code);
            if ($purpose === 'baggage') {
                $query->where(function ($q) use ($db) {
                    $q->where('type_code', 'BELT');
                    if (Schema::connection('ops')->hasTable('infrastructure_zones')) {
                        $q->orWhere(fn ($zones) => $zones->where('type_code', 'ZONE')->whereIn('canonical_id', $db->table('infrastructure_zones')->whereIn('zone_function', ['BAGGAGE_CLAIM', 'BAG_SORTING', 'BAG_MAKEUP', 'OVERSIZE_BAGGAGE'])->select('infrastructure_id')));
                    }
                });
            }
            if ($request->filled('q')) {
                $repository->search($query, $request->query('q'));
            }
            $items = $query->orderBy('code')->paginate(12)->withQueryString();
            if ($purpose === 'ground') {
                $equipment = $db->table('ground_equipment as e')->leftJoin('airport_infrastructure as i', 'i.id', '=', 'e.location_infrastructure_id')->where('e.airport_code', $airport->code)->orderBy('e.code')->get(['e.*', 'i.code as location_code', 'i.name as location_name']);
            }
        }
        $selectedEquipment = $request->filled('equipment_id') ? ($equipment->firstWhere('id', (int) $request->query('equipment_id')) ?? abort(404)) : null;
        $parentNames = $airport ? $db->table('airport_infrastructure')->where('airport_code', $airport->code)->get(['id', 'code', 'name', 'parent_id', 'type_code', 'airport_code'])->keyBy('id') : collect();
        $data = compact('purpose', 'choices', 'airport', 'items', 'equipment', 'selectedEquipment', 'parentNames');

        return $request->expectsJson() ? response()->json($data) : view('m01.infrastructure-reference', $data);
    }

    public function location(Request $request, int $id)
    {
        $this->authorizePurpose($request, 'ground');
        $input = $request->validate(['location_infrastructure_id' => 'nullable|integer|min:1']);
        DB::connection('ops')->transaction(function () use ($request, $id, $input) {
            $db = DB::connection('ops');
            $equipment = $db->table('ground_equipment')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            Access::airport($request->user(), $equipment->airport_code);
            $locationId = $input['location_infrastructure_id'] ?? null;
            if ($locationId) {
                abort_unless($db->table('airport_infrastructure')->where('airport_code', $equipment->airport_code)->where('id', $locationId)->exists(), 422, 'Vị trí phục vụ phải cùng sân bay.');
            }
            $db->table('ground_equipment')->where('id', $id)->update(['location_infrastructure_id' => $locationId, 'updated_at' => now()]);
            Audit::record('ground.equipment.location', (string) $id, (string) $equipment->location_infrastructure_id, (string) $locationId, $request->user()->id, 'ops');
        });

        return $request->expectsJson() ? response()->json(['id' => $id]) : back()->with('message', 'Đã cập nhật vị trí; không thay đổi phân công thiết bị.');
    }
}
