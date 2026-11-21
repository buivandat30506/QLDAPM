<?php

namespace App\Http\Controllers;

use App\Modules\Operations\InfrastructureRepository;
use App\Modules\Operations\InfrastructureService;
use App\Support\AirportWorkspace;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;
use App\Support\InfrastructureLegacyMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InfrastructureController extends Controller
{
    public function index(Request $request, InfrastructureRepository $repository)
    {
        $choices = AirportWorkspace::airports($request->user())->orderBy('name')->get(['code', 'name']);
        $request->validate(['airport' => 'nullable|regex:/^[A-Z]{3}$/', 'group' => ['nullable', Rule::in(array_keys(InfrastructureCatalog::GROUPS))], 'kind' => ['nullable', Rule::in(array_keys(InfrastructureCatalog::types()))], 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $airport = $request->filled('airport') ? AirportWorkspace::airport($request->user(), $request->query('airport')) : null;
        $items = null;
        $parents = collect();
        $types = collect();
        $groupCounts = collect();
        if ($airport) {
            $groupCounts = $repository->listing($airport->code)->selectRaw('group_code, COUNT(*) AS total')->groupBy('group_code')->pluck('total', 'group_code');
            $query = $repository->listing($airport->code);
            if ($request->filled('group')) {
                $query->where('group_code', $request->query('group'));
            }
            if ($request->filled('kind')) {
                $query->where('type_code', $request->query('kind'));
            }
            if ($request->filled('q')) {
                $repository->search($query, $request->query('q'));
            }
            $items = $query->orderBy('type_code')->orderBy('code')->paginate(12)->withQueryString();
            $types = DB::connection('ops')->table('infrastructure_types')->whereIn('code', array_keys(InfrastructureCatalog::LABELS))->when($request->filled('group'), fn ($q) => $q->where('group_code', $request->query('group')))->orderBy('label')->get();
            $parents = DB::connection('ops')->table('airport_infrastructure')->where('airport_code', $airport->code)->whereIn('type_code', array_keys(array_filter(InfrastructureCatalog::types(), fn ($t) => $t['category'] === 'facility')))->get(['id', 'code', 'name', 'type_code', 'parent_id', 'airport_code'])->keyBy('id');
        }
        $unresolvedLegacy = $airport ? DB::connection('ops')->table('airport_resources')->where('airport_code', $airport->code)->whereNull('infrastructure_id')->count() + DB::connection('ops')->table('airport_areas')->where('airport_code', $airport->code)->whereNull('infrastructure_id')->count() : 0;
        $context = $request->only(['airport', 'group', 'kind', 'q', 'page']) + ['flow' => 'infrastructure'];
        $data = compact('choices', 'airport', 'items', 'parents', 'types', 'context', 'unresolvedLegacy', 'groupCounts') + ['unified' => true];

        return $request->expectsJson() ? response()->json($data) : view('m01.infrastructure', $data);
    }

    private function choices(string $airport, string $type, ?int $id = null): array
    {
        $db = DB::connection('ops');

        $parents = $db->table('airport_infrastructure as i')->join('infrastructure_types as t', 't.code', '=', 'i.type_code')->where('i.airport_code', $airport)->where('t.category', 'facility')->orderBy('i.code')->get(['i.id', 'i.type_code', 'i.parent_id', 'i.code', 'i.name', 'i.airport_code', 't.label']);

        return ['parentChoices' => InfrastructureDisplay::parentChoices($parents, $type, $id), 'parentRecords' => $parents->keyBy('id'), 'standChoices' => $db->table('airport_infrastructure')->where('airport_code', $airport)->where('type_code', 'STAND')->orderBy('code')->get(), 'gateChoices' => $db->table('airport_infrastructure')->where('airport_code', $airport)->where('type_code', 'GATE')->orderBy('code')->get()];
    }

    public function createForm(Request $request)
    {
        AirportWorkspace::airports($request->user());
        $request->validate(['airport' => 'required|regex:/^[A-Z]{3}$/', 'type' => ['required', Rule::in(array_keys(InfrastructureCatalog::types()))]]);
        $airport = AirportWorkspace::airport($request->user(), $request->query('airport'));
        $type = InfrastructureCatalog::types()[$request->query('type')];

        return view('m01.infrastructure-form', ['airport' => $airport, 'type' => $type, 'record' => null, 'profile' => [], 'directions' => [], 'linkedStands' => [], 'linkedGates' => [], 'context' => ['flow' => 'infrastructure', 'airport' => $airport->code, 'group' => $type['group_code']]] + $this->choices($airport->code, $type['code']));
    }

    private function payload(Request $request): array
    {
        $data = $request->all();
        if (isset($data['direction_text'])) {
            $data['directions'] = preg_split('/[\s,]+/', trim($data['direction_text']), -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach (['serves_departures', 'serves_arrivals', 'technical_state', 'parent_id', 'length_m', 'width_m'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        if (($data['type_code'] ?? '') === 'GATE') {
            $data['stand_ids'] = $data['stand_ids'] ?? [];
        }
        if (($data['type_code'] ?? '') === 'JET_BRIDGE') {
            $data['stand_ids'] = $data['stand_ids'] ?? [];
            $data['gate_ids'] = $data['gate_ids'] ?? [];
        }

        return $data;
    }

    public function store(Request $request, InfrastructureService $service)
    {
        AirportWorkspace::airports($request->user());
        $request->validate(['airport_code' => 'required|regex:/^[A-Z]{3}$/']);
        AirportWorkspace::airport($request->user(), (string) $request->input('airport_code'));
        $result = $service->create($request->user(), $this->payload($request));

        return $request->expectsJson() ? response()->json($result, 201) : redirect('/workspace/catalog/infrastructure/items/'.$result->id)->with('message', 'Đã tạo hồ sơ hạ tầng.');
    }

    public function show(Request $request, int $id, InfrastructureService $service)
    {
        $allowed = AirportWorkspace::airports($request->user())->select('code');
        $db = DB::connection('ops');
        $record = $db->table('airport_infrastructure')->whereIn('airport_code', $allowed)->where('id', $id)->first() ?? abort(404);
        $airport = AirportWorkspace::airport($request->user(), $record->airport_code);
        $snapshot = $service->snapshot($id);
        $legacyType = ! isset(InfrastructureCatalog::LABELS[$record->type_code]);
        $type = InfrastructureCatalog::types()[$record->type_code] ?? ['code' => $record->type_code, 'label' => InfrastructureCatalog::label($record->type_code), 'group_code' => ''];
        $displayType = InfrastructureLegacyMap::CONVERSIONS[$record->type_code][0] ?? $record->type_code;
        $profile = (array) ($snapshot['profiles'][InfrastructureCatalog::PROFILE_TABLES[$displayType] ?? 'none'] ?? []);
        $directions = array_values(array_map(fn ($row) => $row->designator, array_filter($snapshot['directions'], fn ($row) => $row->active)));
        $linkedStands = array_values(array_map(fn ($row) => $row->target_id, array_filter($snapshot['links'], fn ($row) => in_array($row->relation, ['GATE_STAND', 'JET_BRIDGE_STAND'], true))));
        $linkedGates = array_values(array_map(fn ($row) => $row->target_id, array_filter($snapshot['links'], fn ($row) => $row->relation === 'JET_BRIDGE_GATE')));
        $children = $db->table('airport_infrastructure')->where('parent_id', $id)->orderBy('code')->paginate(10, ['*'], 'children_page')->withQueryString();
        $parent = $record->parent_id ? $db->table('airport_infrastructure')->find($record->parent_id) : null;
        $resource = $db->table('airport_resources')->where('infrastructure_id', $id)->first();
        $allocations = $resource ? $db->table('resource_allocations as a')->join('flights as f', 'f.id', '=', 'a.flight_id')->where('a.resource_id', $resource->id)->orderByDesc('a.starts_at')->paginate(10, ['a.*', 'f.number'], 'schedule_page')->withQueryString() : null;
        $scheduleAt = now();
        $scheduledNow = $resource ? $db->table('resource_allocations')->where('resource_id', $resource->id)->where('starts_at', '<=', $scheduleAt)->where('ends_at', '>', $scheduleAt)->count() : 0;
        $events = $db->table('audit_logs')->where('object_id', (string) $id)->whereIn('action', ['infrastructure.created', 'infrastructure.updated', 'infrastructure.legacy_linked', 'infrastructure.status.initialized', 'infrastructure.classification.normalized'])->latest('id')->limit(20)->get();
        $legacyArea = $db->table('airport_areas')->where('infrastructure_id', $id)->first();
        $changes = $db->table('master_changes')->where(function ($query) use ($id, $resource) {
            $query->where(fn ($q) => $q->where('entity', 'airport_infrastructure')->where('record_id', (string) $id));
            if ($resource) {
                $query->orWhere(fn ($q) => $q->where('entity', 'airport_resources')->where('record_id', (string) $resource->id));
            }
        })->latest('id')->limit(20)->get();
        $context = $request->only(['group', 'kind', 'q', 'page']) + ['flow' => 'infrastructure', 'airport' => $airport->code];
        $data = compact('record', 'airport', 'type', 'profile', 'directions', 'linkedStands', 'linkedGates', 'children', 'parent', 'resource', 'allocations', 'events', 'changes', 'context', 'legacyArea', 'scheduledNow', 'scheduleAt', 'legacyType', 'displayType') + $this->choices($airport->code, $record->type_code, (int) $record->id);

        return $request->expectsJson() ? response()->json($data) : view('m01.infrastructure-detail', $data);
    }

    public function propose(Request $request, int $id, InfrastructureService $service)
    {
        AirportWorkspace::airports($request->user());
        $row = DB::connection('ops')->table('airport_infrastructure')->find($id) ?? abort(404);
        AirportWorkspace::airport($request->user(), $row->airport_code);
        $request->validate(['reason' => 'required|string|min:5|max:255']);
        $data = $this->payload($request);
        $data['airport_code'] = $row->airport_code;
        $data['code'] = $row->code;
        $data['type_code'] = $row->type_code;
        $result = $service->propose($request->user(), $id, $data, $request->input('reason'));

        return $request->expectsJson() ? response()->json($result, 201) : back()->with('message', 'Đã gửi đề nghị; cần người duyệt độc lập.');
    }
}
