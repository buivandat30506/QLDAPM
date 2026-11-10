<?php

namespace App\Http\Controllers;

use App\Modules\Operations\InfrastructureCompatibility;
use App\Modules\Operations\InfrastructureRepository;
use App\Modules\Operations\MasterDataService;
use App\Modules\Shared\Audit;
use App\Support\AirportStatusAuthority;
use App\Support\AirportWorkspace;
use App\Support\AirportProfile;
use App\Support\AirportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AirportController extends Controller
{
    private function context(Request $request, string $flow): array
    {
        return $request->only($flow === 'airports' ? ['q', 'page'] : ['airport', 'source', 'kind', 'q', 'page']) + ['flow' => $flow];
    }

    public function index(Request $request)
    {
        if ($request->query('flow') === 'infrastructure' && ! $request->has('source') && ! $request->expectsJson() && app(InfrastructureCompatibility::class)->ready()) {
            return app(InfrastructureController::class)->index($request, app(InfrastructureRepository::class));
        }
        AirportWorkspace::airports($request->user(), $request->query('flow', 'airports') === 'airports');
        $request->validate([
            'flow' => ['sometimes', 'required', Rule::in(['airports', 'infrastructure'])],
            'q' => 'nullable|string|max:100', 'airport' => 'nullable|regex:/^[A-Z]{3}$/',
            'source' => ['sometimes', Rule::in(['resources', 'areas'])],
            'kind' => ['nullable', Rule::in(array_keys(AirportWorkspace::TYPES))],
            'page' => 'nullable|integer|min:1', 'imports_page' => 'nullable|integer|min:1',
        ]);
        $flow = $request->query('flow', 'airports');
        if ($flow === 'airports') {
            $airports = AirportWorkspace::airports($request->user(), true);
            if ($request->filled('q')) {
                $term = '%'.addcslashes($request->query('q'), '\\%_').'%';
                $airports->where(fn ($query) => $query->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('city', 'like', $term));
            }
            $rows = $airports->orderBy('code')->paginate(12)->withQueryString();
            $pendingImports = DB::connection('ops')->table('master_changes')->where('entity', 'airports')->whereRaw("JSON_TYPE(before_data) = 'NULL'")->where('status', 'PENDING')->latest('id')->paginate(10, ['*'], 'imports_page')->withQueryString();

            return $request->expectsJson() ? response()->json(['flow' => $flow, 'airports' => $rows])
                : view('m01.index', ['airports' => $rows, 'pendingImports' => $pendingImports, 'context' => $this->context($request, $flow)]);
        }

        $choices = AirportWorkspace::airports($request->user())->orderBy('name')->get(['code', 'name']);
        $airport = $request->filled('airport') ? AirportWorkspace::airport($request->user(), $request->query('airport')) : null;
        $source = $request->query('source', 'resources');
        $items = null;
        $parents = collect();
        $types = [];
        if ($airport) {
            $table = $source === 'areas' ? 'airport_areas' : 'airport_resources';
            $base = DB::connection('ops')->table($table)->where('airport_code', $airport->code);
            $types = (clone $base)->distinct()->orderBy('type')->pluck('type')->all();
            if ($request->filled('kind')) {
                $base->where('type', $request->query('kind'));
            }
            if ($request->filled('q')) {
                $term = '%'.addcslashes($request->query('q'), '\\%_').'%';
                $base->where(fn ($query) => $query->where('code', 'like', $term)->when($source === 'areas', fn ($query) => $query->orWhere('name', 'like', $term)));
            }
            $items = $base->orderBy('type')->orderBy('code')->paginate(12)->withQueryString();
            $parents = DB::connection('ops')->table('airport_areas')->where('airport_code', $airport->code)->pluck('name', 'id');
        }
        $data = compact('choices', 'airport', 'source', 'items', 'parents', 'types') + ['context' => $this->context($request, $flow)];

        return $request->expectsJson() ? response()->json($data) : view('m01.infrastructure', $data);
    }

    public function show(Request $request, string $airport)
    {
        $record = AirportWorkspace::airport($request->user(), $airport, true);
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'history_page'=>'nullable|integer|min:1', 'system_page'=>'nullable|integer|min:1', 'edit_proposal'=>'nullable|in:1']);
        $resources = DB::connection('ops')->table('airport_resources')->where('airport_code', $airport)->select('type')->selectRaw('COUNT(*) AS total')->groupBy('type')->get();
        $areas = DB::connection('ops')->table('airport_areas')->where('airport_code', $airport)->select('type')->selectRaw('COUNT(*) AS total')->groupBy('type')->get();
        $changes = DB::connection('ops')->table('master_changes')->where('entity', 'airports')->where('record_id', $airport)->latest('id')->limit(10)->get();
        $events = DB::connection('ops')->table('audit_logs')->where('object_id', $airport)->whereIn('action', ['airport.created', 'airport.updated', 'airport.identifiers.imported', 'airport.operational_status.updated', 'airport.operational_status.simulated', 'airport.operational_status.simulation.cleared'])->latest('id')->limit(10)->get();
        $historyData = AirportHistory::pages($airport, \App\Modules\Shared\Access::allows($request->user(),'data_admin'));
        $actorIds = $events->pluck('actor_id')->merge($changes->pluck('created_by'))->merge($changes->pluck('approved_by'))->merge($historyData['history']->pluck('created_by'))->merge($historyData['history']->pluck('approved_by'))->push($record->operational_status_updated_by ?? null)->filter()->unique();
        $actors = DB::table('users')->whereIn('id', $actorIds)->pluck('name', 'id');
        $statusReady = Schema::connection('ops')->hasColumn('airports', 'operational_status');
        $localTime = in_array($record->timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? now($record->timezone) : null;
        $data = ['airport' => $record, 'resources' => $resources, 'areas' => $areas, 'changes' => $changes, 'events' => $events, 'actors' => $actors, 'statusReady' => $statusReady, 'localTime' => $localTime, 'context' => $this->context($request, 'airports')];
        $draft = $request->hasSession() ? $request->session()->get('airport_profile_preview.'.$airport) : null;
        $data['draft'] = $request->boolean('edit_proposal') && $draft && $draft['actor'] === $request->user()->id && $draft['expires'] >= now()->timestamp ? $draft['data'] : [];
        $data += $historyData;

        return $request->expectsJson() ? response()->json($data) : view('m01.airport-detail', $data);
    }

    public function status(Request $request, string $airport)
    {
        abort_unless(AirportStatusAuthority::canUpdate($request->user(), $airport), 403, 'Cần quyền cập nhật trạng thái của Giám đốc trong đúng phạm vi sân bay.');
        $data = $request->validate(['operational_status' => ['required', Rule::in(array_keys(AirportWorkspace::STATUSES))], 'note' => 'required|string|min:5|max:500', 'confirmed' => 'required|accepted']);
        abort_unless(Schema::connection('ops')->hasColumn('airports', 'operational_status'), 409, 'Chưa cài đặt dữ liệu trạng thái vận hành.');
        DB::connection('mysql')->transaction(function () use ($request, $airport, $data) {
            DB::connection('mysql')->table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            abort_unless(AirportStatusAuthority::canUpdate($request->user(), $airport), 403);
            DB::connection('ops')->transaction(function () use ($request, $airport, $data) {
                $db = DB::connection('ops');
                $current = $db->table('airports')->where('code', $airport)->lockForUpdate()->first() ?? abort(404);
                $changes = ['operational_status' => $data['operational_status'], 'operational_status_note' => $data['note'], 'operational_status_updated_by' => $request->user()->id, 'operational_status_updated_at' => now(), 'operational_status_source' => 'MANUAL', 'operational_status_simulation_key' => null, 'operational_status_simulation_fingerprint' => null];
                $db->table('airports')->where('code', $airport)->update($changes);
                Audit::record('airport.operational_status.updated', $airport, json_encode(array_intersect_key((array) $current, array_flip(array_keys($changes))), JSON_THROW_ON_ERROR), json_encode($changes, JSON_THROW_ON_ERROR), $request->user()->id, 'ops');
            }, 3);
        }, 3);

        return $request->expectsJson() ? response()->json(['status' => $data['operational_status']]) : back()->with('message', 'Đã ghi nhận trạng thái vận hành sân bay.');
    }

    public function proposeAirport(Request $request, string $airport, MasterDataService $master)
    {
        AirportWorkspace::airport($request->user(), $airport);
        $request->validate(['preview_token'=>'nullable|string|max:100']);
        return DB::connection('ops')->transaction(function () use ($request,$airport,$master) {
            $record = DB::connection('ops')->table('airports')->where('code',$airport)->lockForUpdate()->first() ?? abort(404);
            if ($request->filled('preview_token')) {
                $preview = $request->session()->get('airport_profile_preview.'.$airport);
                abort_unless($preview && $preview['actor'] === $request->user()->id && $preview['expires'] >= now()->timestamp && hash_equals($preview['token'], (string)$request->input('preview_token')), 409, 'Bản xem trước đã hết hạn hoặc đã được gửi. Vui lòng xem trước lại.');
                if ($preview['before'] !== AirportProfile::snapshot($record)) {
                    return redirect('/workspace/catalog/airports/'.$airport.'?edit_proposal=1#airport-edit')->withErrors(['changes'=>'Hồ sơ đã được cập nhật. Hãy đối chiếu và xem trước lại trước khi gửi.']);
                }
                $data = $preview['data'];
            } else {
                $data = $this->profileInput($request, $record);
            }
            $result = $master->propose($request->user(), ['entity' => 'airports', 'record_id' => $airport, 'changes' => array_diff_key($data, ['reason' => true]), 'reason' => $data['reason']]);
            $request->session()->forget('airport_profile_preview.'.$airport);

            return $request->expectsJson() ? response()->json($result, 201) : redirect('/workspace/catalog/airports/'.$airport.'#airport-history')->with('message', 'Đã gửi đề nghị sửa thông tin. Cần người khác phê duyệt.');
        }, 3);
    }

    private function profileInput(Request $request, object $record): array
    {
        $data = $request->validate(AirportProfile::rules(), [], AirportProfile::FIELDS + ['reason'=>'lý do sửa']);
        if (! AirportProfile::differences(AirportProfile::snapshot($record), array_diff_key($data,['reason'=>true]))) {
            throw ValidationException::withMessages(['changes'=>'Chưa có thông tin nào thay đổi.']);
        }
        return $data;
    }

    public function previewAirport(Request $request, string $airport)
    {
        $record = AirportWorkspace::airport($request->user(), $airport);
        $data = $this->profileInput($request, $record);
        $before = AirportProfile::snapshot($record);
        $token = Str::random(48);
        $request->session()->put('airport_profile_preview.'.$airport,['actor'=>$request->user()->id,'token'=>$token,'before'=>$before,'data'=>$data,'expires'=>now()->addMinutes(30)->timestamp]);
        $payload = ['airport'=>$record,'data'=>$data,'differences'=>AirportProfile::differences($before,array_diff_key($data,['reason'=>true])),'token'=>$token];
        return $request->expectsJson() ? response()->json($payload) : view('m01.airport-edit-preview',$payload);
    }

    public function resource(Request $request, string $source, int $id)
    {
        abort_unless(in_array($source, ['resources', 'areas'], true), 404);
        $allowed = AirportWorkspace::airports($request->user())->select('code');
        $table = $source === 'areas' ? 'airport_areas' : 'airport_resources';
        $record = DB::connection('ops')->table($table)->whereIn('airport_code', $allowed)->where('id', $id)->first() ?? abort(404);
        $airport = AirportWorkspace::airport($request->user(), $record->airport_code);
        $parent = $source === 'areas' && $record->parent_id ? DB::connection('ops')->table('airport_areas')->where('airport_code', $airport->code)->where('id', $record->parent_id)->first() : null;
        $allocations = $source === 'resources' ? DB::connection('ops')->table('resource_allocations as a')->join('flights as f', 'f.id', '=', 'a.flight_id')->where('a.resource_id', $id)->orderByDesc('a.starts_at')->limit(10)->get(['a.*', 'f.number']) : collect();
        $changes = $source === 'resources' ? DB::connection('ops')->table('master_changes')->where('entity', 'airport_resources')->where('record_id', (string) $id)->latest('id')->limit(10)->get() : collect();
        $request->validate(['q' => 'nullable|string|max:100', 'kind' => ['nullable', Rule::in(array_keys(AirportWorkspace::TYPES))], 'page' => 'nullable|integer|min:1']);
        $context = $this->context($request, 'infrastructure');
        $context['airport'] = $airport->code;
        $context['source'] = $source;
        $data = compact('record', 'airport', 'source', 'parent', 'allocations', 'changes', 'context');

        return $request->expectsJson() ? response()->json($data) : view('m01.resource-detail', $data);
    }

    public function proposeResource(Request $request, int $id, MasterDataService $master)
    {
        $allowed = AirportWorkspace::airports($request->user())->select('code');
        $record = DB::connection('ops')->table('airport_resources')->whereIn('airport_code', $allowed)->where('id', $id)->first() ?? abort(404);
        AirportWorkspace::airport($request->user(), $record->airport_code);
        $data = $request->validate(['active' => 'required|boolean', 'reason' => 'required|string|min:5|max:255']);
        $result = $master->propose($request->user(), ['entity' => 'airport_resources', 'record_id' => (string) $id, 'changes' => ['active' => $data['active']], 'reason' => $data['reason']]);

        return $request->expectsJson() ? response()->json($result, 201) : back()->with('message', 'Đã gửi đề nghị thay đổi khả dụng tài nguyên.');
    }
}
