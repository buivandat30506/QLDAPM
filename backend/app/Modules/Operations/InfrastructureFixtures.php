<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use App\Support\CorePruning;
use App\Support\InfrastructureCatalog;
use Illuminate\Support\Facades\DB;

final class InfrastructureFixtures
{
    public const VERSION = 'infrastructure-local-v1';

    public const MISSING_VERSION = 'infrastructure-local-missing-v1';

    public const MISSING_NOTE = 'LOCAL TEST SCENARIO ONLY; unverified airport inventory. Internal identifiers are not operational runway designations. No real technical specifications.';

    public const NOTE = 'LOCAL TEST SCENARIO ONLY; not verified airport infrastructure. Assumed layout and runway directions; no real BMV technical specifications.';

    public function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('infrastructure.local_fixtures_enabled'), 403, 'Cần local/testing và bật AIRLINE_INFRASTRUCTURE_FIXTURES cho phiên lệnh.');
        abort_unless(in_array(config('database.connections.ops.host'), ['db1', 'localhost', '127.0.0.1', '::1'], true), 403, 'Chỉ cho phép cơ sở dữ liệu cục bộ.');
        abort_unless(in_array(config('database.connections.ops.database'), ['airline', 'airline_test', CorePruning::TARGET], true), 403, 'Database không thuộc phạm vi local/test được cho phép.');
    }

    public function plan(): array
    {
        return [
            'terminal' => ['code' => 'LCL-T1', 'name' => 'Nhà ga A', 'type_code' => 'TERMINAL', 'terminal_kind' => 'PASSENGER', 'terminal_scope' => 'DOMESTIC', 'serves_departures' => true, 'serves_arrivals' => true],
            'departure' => ['code' => 'LCL-Z-DEP', 'name' => 'Khu khởi hành A', 'type_code' => 'ZONE', 'zone_function' => 'DEPARTURE_HALL', 'parent' => 'terminal'],
            'checkin' => ['code' => 'LCL-Z-CHECKIN', 'name' => 'Khu làm thủ tục A', 'type_code' => 'ZONE', 'zone_function' => 'CHECKIN', 'parent' => 'terminal'],
            'claim' => ['code' => 'LCL-Z-CLAIM', 'name' => 'Khu nhận hành lý A', 'type_code' => 'ZONE', 'zone_function' => 'BAGGAGE_CLAIM', 'parent' => 'terminal'],
            'apron' => ['code' => 'LCL-APRON', 'name' => 'Sân đỗ A', 'type_code' => 'APRON', 'description' => 'Sân đỗ phục vụ nhà ga A.'],
            'stand' => ['code' => 'LCL-P01', 'name' => 'Vị trí đỗ A01', 'type_code' => 'STAND', 'parent' => 'apron', 'compatibility_notes' => 'Cấu hình tiếp nhận nhóm tàu bay A.'],
            'gate' => ['code' => 'LCL-G01', 'name' => 'Cổng A01', 'type_code' => 'GATE', 'parent' => 'departure', 'stands' => ['stand'], 'serves_departures' => true, 'serves_arrivals' => true],
            'counter' => ['code' => 'LCL-C01', 'name' => 'Quầy làm thủ tục A01', 'type_code' => 'COUNTER', 'parent' => 'checkin', 'counter_service' => 'COMBINED'],
            'bridge' => ['code' => 'LCL-JB01', 'name' => 'Cầu ống lồng A01', 'type_code' => 'JET_BRIDGE', 'parent' => 'departure', 'gates' => ['gate'], 'stands' => ['stand']],
            'belt' => ['code' => 'LCL-B01', 'name' => 'Băng chuyền hành lý A01', 'type_code' => 'BELT', 'parent' => 'claim', 'baggage_flow' => 'ARRIVAL'],
            'runway' => ['code' => 'LCL-RWY01', 'name' => 'Đường băng A', 'type_code' => 'RUNWAY', 'directions' => ['01', '19']],
            'taxiway' => ['code' => 'LCL-TWY01', 'name' => 'Đường lăn A', 'type_code' => 'TAXIWAY', 'taxiway_function' => 'GENERAL'],
        ];
    }

    private function owned(int $id): array
    {
        $db = DB::connection('ops');

        return ['snapshot' => app(InfrastructureService::class)->snapshot($id), 'sources' => $db->table('infrastructure_sources')->where('infrastructure_id', $id)->orderBy('id')->get()->all(), 'resources' => $db->table('airport_resources')->where('infrastructure_id', $id)->orderBy('id')->get()->all()];
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function missingPlan(string $airport): array
    {
        $db = DB::connection('ops');
        foreach (['airport_resources', 'airport_areas'] as $table) {
            abort_if($db->table($table)->where('airport_code', $airport)->whereNull('infrastructure_id')->exists(), 409, 'Cần xác minh ánh xạ nguồn cũ trước khi bổ sung loại còn thiếu.');
        }
        $existing = $db->table('airport_infrastructure')->where('airport_code', $airport)->orderBy('id')->get();
        $definitions = [
            'terminal' => ['code' => 'LCL-T01', 'name' => 'Nhà ga T01', 'type_code' => 'TERMINAL', 'terminal_kind' => 'PASSENGER'],
            'claim' => ['code' => 'LCL-Z01', 'name' => 'Khu nhận hành lý Z01', 'type_code' => 'ZONE', 'zone_function' => 'BAGGAGE_CLAIM', 'parent' => 'terminal'],
            'apron' => ['code' => 'LCL-AP01', 'name' => 'Sân đỗ AP01', 'type_code' => 'APRON'],
            'stand' => ['code' => 'LCL-P01', 'name' => 'Vị trí đỗ P01', 'type_code' => 'STAND', 'parent' => 'apron'],
            'gate' => ['code' => 'LCL-G01', 'name' => 'Cổng G01', 'type_code' => 'GATE', 'parent' => 'terminal', 'serves_departures' => true, 'serves_arrivals' => true],
            'counter' => ['code' => 'LCL-C01', 'name' => 'Quầy làm thủ tục C01', 'type_code' => 'COUNTER', 'parent' => 'terminal', 'counter_service' => 'COMBINED'],
            'bridge' => ['code' => 'LCL-JB01', 'name' => 'Cầu ống lồng JB01', 'type_code' => 'JET_BRIDGE', 'parent' => 'terminal'],
            'belt' => ['code' => 'LCL-B01', 'name' => 'Băng chuyền hành lý B01', 'type_code' => 'BELT', 'baggage_flow' => 'ARRIVAL', 'parent' => 'claim'],
            'runway' => ['code' => 'LCL-RWY01', 'name' => 'Đường băng RWY01', 'type_code' => 'RUNWAY'],
            'taxiway' => ['code' => 'LCL-TWY01', 'name' => 'Đường lăn TWY01', 'type_code' => 'TAXIWAY', 'taxiway_function' => 'GENERAL'],
        ];
        $plan = [];
        $references = [];
        foreach ($definitions as $alias => $definition) {
            $row = $existing->firstWhere('type_code', $definition['type_code']);
            if ($row) {
                $references[$alias] = $row->id;
            } else {
                $plan[$alias] = $definition;
            }
        }
        // An existing zone is only a baggage parent when its function has been explicitly recorded.
        if (isset($references['claim'])) {
            $claim = $db->table('airport_infrastructure as i')->join('infrastructure_zones as z', 'z.infrastructure_id', '=', 'i.id')->where('i.airport_code', $airport)->where('i.type_code', 'ZONE')->where('z.zone_function', 'BAGGAGE_CLAIM')->orderBy('i.id')->value('i.id');
            if ($claim) {
                $references['claim'] = $claim;
            } elseif (isset($plan['belt'])) {
                $plan['belt']['parent'] = 'terminal';
            }
        }
        // Only new, explicitly designed endpoints receive fixture associations. Existing endpoints remain unassigned.
        if (isset($plan['gate'], $plan['stand'])) {
            $plan['gate']['stands'] = ['stand'];
        }
        if (isset($plan['bridge'], $plan['gate'])) {
            $plan['bridge']['gates'] = ['gate'];
        }
        if (isset($plan['bridge'], $plan['stand'])) {
            $plan['bridge']['stands'] = ['stand'];
        }

        return [$plan, $references];
    }

    public function runAll(bool $apply = false, bool $clear = false, ?User $actor = null): array
    {
        $this->guard();
        if ($apply) {
            abort_unless($actor, 422, 'Cần chỉ định --actor là người quản trị dữ liệu.');
            Access::role($actor, 'data_admin');
        }

        return DB::connection('ops')->transaction(function () use ($apply, $clear, $actor) {
            $airports = DB::connection('ops')->table('airports')->orderBy('code')->pluck('code');
            $results = [];
            foreach ($airports as $airport) {
                $results[$airport] = $this->run($airport, false, $clear, null, true);
            }
            $blocked = array_filter($results, fn ($result) => ! empty($result['blocked']));
            if ($apply && ! $blocked) {
                foreach ($airports as $airport) {
                    $results[$airport] = $this->run($airport, true, $clear, $actor, true);
                }
            }

            return ['airports' => $airports->count(), 'created' => array_sum(array_column($results, 'created')), 'planned' => array_sum(array_column($results, 'planned')), 'deleted' => array_sum(array_column($results, 'deleted')), 'blocked' => $blocked, 'results' => $results];
        }, 3);
    }

    public function run(string $airport, bool $apply = false, bool $clear = false, ?User $actor = null, bool $missingOnly = false): array
    {
        $this->guard();
        abort_unless(preg_match('/^[A-Z]{3}$/', $airport), 422, 'Mã sân bay không hợp lệ.');
        if ($apply) {
            abort_unless($actor, 422, 'Cần chỉ định --actor là người quản trị dữ liệu.');
            Access::role($actor, 'data_admin');
            Access::airport($actor, $airport);
        }

        return DB::connection('ops')->transaction(function () use ($airport, $apply, $clear, $actor, $missingOnly) {
            $db = DB::connection('ops');
            $db->table('airports')->where('code', $airport)->lockForUpdate()->first() ?? abort(404, 'Sân bay chưa được khai báo.');
            $version = $missingOnly ? self::MISSING_VERSION : self::VERSION;
            $note = $missingOnly ? self::MISSING_NOTE : self::NOTE;
            $key = $version.':'.$airport;
            $set = $db->table('infrastructure_fixture_sets')->where('key', $key)->lockForUpdate()->first();
            if ($clear) {
                return $this->clear($set, $apply, $actor);
            }
            if ($set && $set->status === 'ACTIVE') {
                $manifest = json_decode($set->manifest, true, 512, JSON_THROW_ON_ERROR);

                return ['created' => 0, 'active' => true, 'ids' => $manifest['ids'], 'message' => 'Bộ dữ liệu đã tồn tại; giữ nguyên cả những hồ sơ người dùng đã chỉnh sửa.'];
            }
            $types = $db->table('infrastructure_types')->orderBy('code')->pluck('code')->all();
            $expected = array_keys(InfrastructureCatalog::LABELS);
            sort($expected);
            abort_unless($types === $expected, 409, 'Cần danh mục chuẩn đúng 10 loại.');
            [$plan, $references] = $missingOnly ? $this->missingPlan($airport) : [$this->plan(), []];
            if (! $plan) {
                return ['created' => 0, 'planned' => 0, 'complete' => true, 'airport' => $airport];
            }
            // An explicit local opt-in must never turn an operational airport dataset into a fixture set.
            $provided = $db->table('infrastructure_sources as s')->join('airport_infrastructure as i', 'i.id', '=', 's.infrastructure_id')->where('i.airport_code', $airport)->where('s.entry_method', '!=', 'LOCAL_FIXTURE')->exists();
            abort_if($provided, 409, 'Sân bay đã có dữ liệu do đơn vị/người dùng cung cấp; không áp dụng bộ thử nghiệm.');
            foreach (['airport_infrastructure', 'airport_resources', 'airport_areas'] as $table) {
                abort_if($db->table($table)->where('airport_code', $airport)->whereIn('code', array_column($plan, 'code'))->exists(), 409, 'Mã của kịch bản đã tồn tại ngoài manifest; không nhận làm dữ liệu thử nghiệm.');
            }
            if (! $apply) {
                return ['created' => 0, 'planned' => count($plan), 'airport' => $airport, 'scenario' => $plan, 'existing_references' => $references, 'source' => $note];
            }
            $ids = [];
            $resolved = $references;
            foreach ($plan as $alias => $definition) {
                $input = array_diff_key($definition, array_flip(['parent', 'stands', 'gates']));
                $input += ['airport_code' => $airport, 'active' => true, 'technical_state' => 'NORMAL', 'source_reference' => $key.' | '.$note];
                if (isset($definition['parent'])) {
                    $input['parent_id'] = $resolved[$definition['parent']];
                }
                foreach (['stands' => 'stand_ids', 'gates' => 'gate_ids'] as $relation => $field) {
                    if (isset($definition[$relation])) {
                        $input[$field] = array_map(fn ($alias) => $resolved[$alias], $definition[$relation]);
                    }
                }
                if ($alias === 'bridge') {
                    $input['asset_reference'] = 'LCL-'.$airport.'-JB01';
                }
                $ids[$alias] = app(InfrastructureService::class)->create($actor, $input, 'LOCAL_FIXTURE')->id;
                $resolved[$alias] = $ids[$alias];
            }
            $manifest = ['version' => $version, 'airport' => $airport, 'environment' => app()->environment(), 'database' => $db->getDatabaseName(), 'source' => $note, 'design' => $plan, 'existing_references' => $references, 'ids' => $ids, 'records' => []];
            foreach ($ids as $id) {
                $manifest['records'][$id] = $this->owned($id);
            }
            $db->table('infrastructure_fixture_sets')->updateOrInsert(['key' => $key], ['airport_code' => $airport, 'status' => 'ACTIVE', 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'created_by' => $actor->id, 'created_at' => now(), 'cleared_at' => null]);
            Audit::record('infrastructure.fixture.created', $key, null, json_encode($manifest), $actor->id, 'ops');

            return ['created' => count($ids), 'airport' => $airport, 'ids' => $ids];
        }, 3);
    }

    private function clear(?object $set, bool $apply, ?User $actor): array
    {
        if (! $set || $set->status !== 'ACTIVE') {
            return ['deleted' => 0, 'eligible' => 0];
        }
        $db = DB::connection('ops');
        $manifest = json_decode($set->manifest, true, 512, JSON_THROW_ON_ERROR);
        $ids = array_values($manifest['ids']);
        $db->table('airport_infrastructure')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $resources = $db->table('airport_resources')->whereIn('infrastructure_id', $ids)->orderBy('id')->lockForUpdate()->pluck('id')->all();
        $blocked = [];
        foreach ($ids as $id) {
            if (! $db->table('airport_infrastructure')->where('id', $id)->exists() || $this->hash($this->owned($id)) !== $this->hash($manifest['records'][$id])) {
                $blocked[] = 'Hồ sơ #'.$id.' đã thay đổi hoặc bị xóa.';
            }
        }
        $dependencies = [
            'Hạng mục bên ngoài trực thuộc' => $db->table('airport_infrastructure')->whereIn('parent_id', $ids)->whereNotIn('id', $ids),
            'Liên kết bên ngoài' => $db->table('infrastructure_links')->whereIn('target_id', $ids)->whereNotIn('source_id', $ids),
            'Khu vực nguồn cũ' => $db->table('airport_areas')->whereIn('infrastructure_id', $ids),
            'Phân bổ M18' => $db->table('resource_allocations')->whereIn('resource_id', $resources),
            'Thiết bị mặt đất' => $db->table('ground_equipment')->whereIn('location_infrastructure_id', $ids),
            'Chuyến đã ghi nhận cổng' => $db->table('flights')->where('origin', $set->airport_code)->whereIn('gate', $db->table('airport_infrastructure')->whereIn('id', $ids)->where('type_code', 'GATE')->select('code')),
            'Lịch sử sử dụng tài nguyên' => $db->table('audit_logs')->where('action', 'infrastructure.fixture.used')->whereIn('object_id', array_map('strval', $ids)),
            'Lịch sử vị trí thiết bị' => $db->table('audit_logs')->where('action', 'ground.equipment.location')->where(fn ($q) => $q->whereIn('before_state', array_map('strval', $ids))->orWhereIn('after_state', array_map('strval', $ids))),
            'Đề nghị thay đổi' => $db->table('master_changes')->where(fn ($q) => $q->where(fn ($q) => $q->where('entity', 'airport_infrastructure')->whereIn('record_id', array_map('strval', $ids)))->orWhere(fn ($q) => $q->where('entity', 'airport_resources')->whereIn('record_id', array_map('strval', $resources)))),
        ];
        foreach ($dependencies as $label => $query) {
            if ($query->exists()) {
                $blocked[] = $label;
            }
        }
        foreach ($db->table('master_changes')->where('status', 'PENDING')->where('entity', 'airport_infrastructure')->get(['after_data']) as $proposal) {
            $data = json_decode($proposal->after_data, true) ?? [];
            if (array_intersect($ids, array_merge([$data['parent_id'] ?? null], $data['gate_ids'] ?? [], $data['stand_ids'] ?? []))) {
                $blocked[] = 'Đề nghị đang tham chiếu bộ dữ liệu';
            }
        }
        // Refuse unknown future module references, rather than relying on cascading deletion.
        $managed = array_merge(['airport_infrastructure', 'airport_resources', 'airport_areas', 'infrastructure_sources', 'infrastructure_links', 'runway_directions'], array_map(fn ($name) => 'infrastructure_'.$name, array_values(InfrastructureCatalog::PROFILE_TABLES)));
        foreach ($db->select("SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, REFERENCED_TABLE_NAME AS target FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IN ('airport_infrastructure','airport_resources') AND REFERENCED_COLUMN_NAME = 'id'", [$db->getDatabaseName()]) as $fk) {
            if (! in_array($fk->table_name, $managed, true) && $db->table($fk->table_name)->whereIn($fk->column_name, $fk->target === 'airport_resources' ? $resources : $ids)->exists()) {
                $blocked[] = 'Tham chiếu nghiệp vụ: '.$fk->table_name;
            }
        }
        if ($blocked || ! $apply) {
            return ['deleted' => 0, 'eligible' => $blocked ? 0 : count($ids), 'blocked' => array_values(array_unique($blocked))];
        }
        $db->table('infrastructure_links')->whereIn('source_id', $ids)->delete();
        $db->table('runway_directions')->whereIn('runway_id', $ids)->delete();
        foreach (array_unique(InfrastructureCatalog::PROFILE_TABLES) as $profile) {
            $db->table('infrastructure_'.$profile)->whereIn('infrastructure_id', $ids)->delete();
        }
        $db->table('infrastructure_sources')->whereIn('infrastructure_id', $ids)->delete();
        $db->table('airport_resources')->whereIn('infrastructure_id', $ids)->delete();
        $db->table('airport_infrastructure')->whereIn('id', $ids)->update(['parent_id' => null]);
        $db->table('airport_infrastructure')->whereIn('id', $ids)->delete();
        $db->table('infrastructure_fixture_sets')->where('key', $set->key)->update(['status' => 'CLEARED', 'cleared_at' => now()]);
        Audit::record('infrastructure.fixture.cleared', $set->key, $set->manifest, 'CLEARED', $actor->id, 'ops');

        return ['deleted' => count($ids), 'eligible' => 0];
    }
}
