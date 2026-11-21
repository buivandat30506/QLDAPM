<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InfrastructureService
{
    public function validate(array $input, ?object $current = null, ?array $legacyIgnore = null): array
    {
        $rules = [
            'airport_code' => 'required|regex:/^[A-Z]{3}$/', 'code' => 'required|string|max:60', 'name' => 'nullable|string|max:160',
            'type_code' => ['required', Rule::in(array_keys(InfrastructureCatalog::types()))], 'parent_id' => 'nullable|integer|min:1',
            'active' => 'required|boolean', 'technical_state' => ['nullable', Rule::in(array_keys(InfrastructureCatalog::CONDITIONS))], 'description' => 'nullable|string|max:4000',
            'serves_departures' => 'nullable|boolean', 'serves_arrivals' => 'nullable|boolean', 'length_m' => 'nullable|numeric|gt:0|decimal:0,2|max:99999999.99', 'width_m' => 'nullable|numeric|gt:0|decimal:0,2|max:99999999.99', 'limitations' => 'nullable|string|max:4000',
            'directions' => 'sometimes|array|max:20', 'directions.*' => 'required|string|regex:/^[A-Z0-9-]{1,12}$/|distinct',
            'compatibility_notes' => 'nullable|string|max:4000', 'asset_reference' => 'nullable|string|max:120', 'stand_ids' => 'sometimes|array|max:100', 'stand_ids.*' => 'required|integer|min:1|distinct',
            'gate_ids' => 'sometimes|array|max:100', 'gate_ids.*' => 'required|integer|min:1|distinct',
            'source_reference' => 'nullable|string|max:500',
        ];
        foreach (InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field => $definition) {
            $rules[$field] = ['nullable', Rule::in(array_keys($definition['options']))];
        }
        $attributes = ['airport_code'=>'sân bay', 'code'=>'mã hạ tầng', 'name'=>'tên hạ tầng', 'type_code'=>'loại hạ tầng', 'parent_id'=>'vị trí trực thuộc', 'active'=>'hiệu lực', 'technical_state'=>'trạng thái vận hành', 'description'=>'mô tả', 'serves_departures'=>'khả năng phục vụ khởi hành', 'serves_arrivals'=>'khả năng phục vụ chuyến đến', 'length_m'=>'chiều dài', 'width_m'=>'chiều rộng', 'limitations'=>'giới hạn', 'directions'=>'hướng khai thác', 'directions.*'=>'hướng khai thác', 'compatibility_notes'=>'thông tin phục vụ máy bay', 'asset_reference'=>'mã tài sản', 'gate_ids'=>'cổng liên kết', 'gate_ids.*'=>'cổng liên kết', 'stand_ids'=>'vị trí đỗ liên kết', 'stand_ids.*'=>'vị trí đỗ liên kết', 'source_reference'=>'nguồn cung cấp'];
        foreach (InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field => $definition) {
            $attributes[$field] = mb_strtolower($definition['label']);
        }
        $data = Validator::make($input, $rules, ['distinct'=>'Mỗi :attribute chỉ được chọn một lần.', 'gt'=>':attribute phải lớn hơn :value.'], $attributes)->validate();
        if ($current) {
            abort_unless($data['airport_code'] === $current->airport_code && $data['code'] === $current->code && $data['type_code'] === $current->type_code, 422, 'Không đổi sân bay, mã hoặc loại của định danh đã tạo.');
        }
        $db = DB::connection('ops');
        $profileFields = InfrastructureCatalog::PROFILE_FIELDS[$data['type_code']];
        foreach (array_unique(array_merge(...array_values(InfrastructureCatalog::PROFILE_FIELDS))) as $field) {
            if (array_key_exists($field, $data) && ! in_array($field, $profileFields, true)) {
                throw ValidationException::withMessages([$field => 'Thuộc tính không áp dụng cho loại hạ tầng này.']);
            }
        }
        abort_unless($db->table('airports')->where('code', $data['airport_code'])->exists(), 422, 'Sân bay không tồn tại.');
        $duplicate = $db->table('airport_infrastructure')->where('airport_code', $data['airport_code'])->where('code', $data['code']);
        if ($current) {
            $duplicate->where('id', '!=', $current->id);
        }
        abort_if($duplicate->exists(), 409, 'Mã hạ tầng đã tồn tại tại sân bay.');
        foreach (['airport_resources', 'airport_areas'] as $table) {
            $legacy = $db->table($table)->where('airport_code', $data['airport_code'])->where('code', $data['code'])->whereNull('infrastructure_id');
            if (($legacyIgnore['table'] ?? null) === $table) {
                $legacy->where('id', '!=', $legacyIgnore['id']);
            }
            abort_if($legacy->exists(), 409, 'Mã còn trong nguồn cũ; cần xác minh ánh xạ trước.');
        }
        if (! empty($data['parent_id'])) {
            $seen = $current ? [$current->id] : [];
            $parentType = $db->table('airport_infrastructure')->where('id', $data['parent_id'])->value('type_code');
            if (! in_array($parentType, InfrastructureDisplay::parentTypes($data['type_code']), true)) {
                throw ValidationException::withMessages(['parent_id' => 'Công trình/khu vực cha không phù hợp với loại hạ tầng đã chọn.']);
            }
            $parentId = $data['parent_id'];
            while ($parentId) {
                abort_if(in_array($parentId, $seen), 422, 'Quan hệ cha/con tạo chu trình.');
                $seen[] = $parentId;
                $parent = $db->table('airport_infrastructure')->where('id', $parentId)->where('airport_code', $data['airport_code'])->lockForUpdate()->first();
                abort_unless($parent, 422, 'Khu vực cha phải cùng sân bay.');
                abort_unless(InfrastructureCatalog::types()[$parent->type_code]['category'] === 'facility', 422, 'Đối tượng cha phải là công trình hoặc khu vực.');
                $parentId = $parent->parent_id;
            }
        }
        foreach (['stand_ids' => 'STAND', 'gate_ids' => 'GATE'] as $field => $targetType) {
            if (! empty($data[$field])) {
                abort_unless($db->table('airport_infrastructure')->where('airport_code', $data['airport_code'])->where('type_code', $targetType)->whereIn('id', $data[$field])->count() === count($data[$field]), 422, 'Đối tượng liên kết phải đúng loại và cùng sân bay.');
            }
        }
        if (! empty($data['asset_reference'])) {
            $asset = $db->table('infrastructure_assets as a')->join('airport_infrastructure as i', 'i.id', '=', 'a.infrastructure_id')->where('i.airport_code', $data['airport_code'])->where('a.asset_reference', $data['asset_reference']);
            if ($current) {
                $asset->where('i.id', '!=', $current->id);
            }
            abort_if($asset->exists(), 409, 'Mã tài sản đã được ghi nhận tại sân bay.');
        }
        if ($current) {
            $data += array_intersect_key((array) $current, array_flip(['parent_id', 'name', 'technical_state', 'description']));
        }
        $data += ['parent_id' => null, 'name' => null, 'technical_state' => null, 'description' => null];
        if ($current && $db->table('airport_areas')->where('infrastructure_id', $current->id)->exists() && ($data['name'] === null || $data['name'] === '')) {
            throw ValidationException::withMessages(['name' => 'Tên khu vực đã có trong nguồn cũ không được để trống.']);
        }

        return $data;
    }

    public function create(User $user, array $input, string $entryMethod = 'AUTHORIZED_ENTRY'): object
    {
        Access::role($user, 'data_admin');
        Access::airport($user, $input['airport_code'] ?? '');

        abort_unless(in_array($entryMethod, ['AUTHORIZED_ENTRY', 'CSV_IMPORT', 'LOCAL_FIXTURE'], true), 422);
        if ($entryMethod === 'LOCAL_FIXTURE') {
            app(InfrastructureFixtures::class)->guard();
        }
        return DB::connection('ops')->transaction(function () use ($user, $input, $entryMethod) {
            $db = DB::connection('ops');
            $db->table('airports')->where('code', $input['airport_code'] ?? '')->lockForUpdate()->first();
            $data = $this->validate($input + ['technical_state' => 'NORMAL']);
            $id = $db->table('airport_infrastructure')->insertGetId($this->core($data) + ['version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->profiles($id, $data);
            $this->recordSource($id, $user, $data, $entryMethod);
            if (in_array($data['type_code'], InfrastructureCatalog::SCHEDULED_TYPES, true)) {
                $db->table('airport_resources')->insert(['airport_code' => $data['airport_code'], 'code' => $data['code'], 'type' => $data['type_code'], 'active' => $data['active'], 'infrastructure_id' => $id]);
            }
            Audit::record('infrastructure.created', (string) $id, null, json_encode($this->snapshot($id)), $user->id, 'ops');

            return (object) ['id' => $id];
        }, 3);
    }

    private function core(array $data): array
    {
        return array_intersect_key($data, array_flip(['airport_code', 'code', 'name', 'type_code', 'parent_id', 'active', 'technical_state', 'description']));
    }

    public function snapshot(int $id): array
    {
        $db = DB::connection('ops');
        $row = (array) ($db->table('airport_infrastructure')->where('id', $id)->first() ?? abort(404));
        $row['profiles'] = [];
        foreach (array_unique(array_values(InfrastructureCatalog::PROFILE_TABLES)) as $profile) {
            $row['profiles'][$profile] = Schema::connection('ops')->hasTable('infrastructure_'.$profile) ? $db->table('infrastructure_'.$profile)->where('infrastructure_id', $id)->first() : null;
        }
        $row['directions'] = $db->table('runway_directions')->where('runway_id', $id)->orderBy('id')->get()->all();
        $row['links'] = $db->table('infrastructure_links')->where('source_id', $id)->orderBy('id')->get()->all();

        return $row;
    }

    private function stable(array $value): array
    {
        // Absent nullable profiles in proposals captured before the additive schema are equivalent to NULL.
        if (isset($value['profiles']) && is_array($value['profiles'])) {
            if (($value['profiles']['terminals']['terminal_scope'] ?? null) === null) {
                unset($value['profiles']['terminals']['terminal_scope']);
            }
            $value['profiles'] = array_filter($value['profiles'], fn ($profile) => $profile !== null);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->stable($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    public function propose(User $user, int $id, array $input, string $reason): object
    {
        Access::role($user, 'data_admin');
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:255'])->validate();

        return DB::connection('ops')->transaction(function () use ($user, $id, $input, $reason) {
            $db = DB::connection('ops');
            $row = $db->table('airport_infrastructure')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            Access::airport($user, $row->airport_code);
            $data = $this->validate($input, $row);
            $changeId = $db->table('master_changes')->insertGetId(['entity' => 'airport_infrastructure', 'record_id' => (string) $id, 'before_data' => json_encode($this->snapshot($id)), 'after_data' => json_encode($data), 'reason' => $reason, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('master.proposed', (string) $changeId, null, 'PENDING', $user->id, 'ops');

            return (object) ['id' => $changeId];
        }, 3);
    }

    public function approve(User $user, int $changeId): object
    {
        Access::role($user, 'data_admin');

        return DB::connection('ops')->transaction(function () use ($user, $changeId) {
            $db = DB::connection('ops');
            $change = $db->table('master_changes')->where('id', $changeId)->lockForUpdate()->first() ?? abort(404);
            abort_unless($change->entity === 'airport_infrastructure' && $change->status === 'PENDING' && (int) $change->created_by !== (int) $user->id, 409, 'Cần người duyệt độc lập.');
            $row = $db->table('airport_infrastructure')->where('id', $change->record_id)->lockForUpdate()->first() ?? abort(404);
            Access::airport($user, $row->airport_code);
            $db->table('airports')->where('code', $row->airport_code)->lockForUpdate()->first();
            abort_unless($this->stable(json_decode($change->before_data, true)) === $this->stable(json_decode(json_encode($this->snapshot($row->id)), true)), 409, 'Dữ liệu nguồn đã đổi; cần lập phiên bản mới.');
            $data = $this->validate(json_decode($change->after_data, true), $row);
            $resource = $db->table('airport_resources')->where('infrastructure_id', $row->id)->lockForUpdate()->first();
            $becomesUnavailable = ! $data['active'] || in_array($data['technical_state'], ['MAINTENANCE', 'OUT_OF_SERVICE'], true);
            $wasUnavailable = ! $row->active || in_array($row->technical_state, ['MAINTENANCE', 'OUT_OF_SERVICE'], true);
            if ($becomesUnavailable && ! $wasUnavailable && $resource) {
                abort_if($db->table('resource_allocations')->where('resource_id', $resource->id)->where('ends_at', '>', now())->exists(), 409, 'Tài nguyên còn phân bổ hiệu lực.');
            }
            $db->table('airport_infrastructure')->where('id', $row->id)->update($this->core($data) + ['version' => $row->version + 1, 'updated_at' => now()]);
            $this->profiles($row->id, $data);
            if (! empty($data['source_reference'])) {
                $this->recordSource($row->id, $user, $data, 'APPROVED_CHANGE');
            }
            $db->table('airport_resources')->where('infrastructure_id', $row->id)->update(['active' => $data['active']]);
            $legacyParent = $data['parent_id'] ? $db->table('airport_areas')->where('infrastructure_id', $data['parent_id'])->value('id') : null;
            $db->table('airport_areas')->where('infrastructure_id', $row->id)->update(['name' => $data['name'], 'active' => $data['active'], 'parent_id' => $legacyParent]);
            $db->table('master_changes')->where('id', $changeId)->update(['status' => 'APPROVED', 'approved_by' => $user->id, 'updated_at' => now()]);
            Audit::record('infrastructure.updated', (string) $row->id, $change->before_data, json_encode($this->snapshot($row->id)), $user->id, 'ops');
            Audit::record('master.approved', (string) $changeId, 'PENDING', 'APPROVED', $user->id, 'ops');

            return (object) ['id' => $changeId];
        }, 3);
    }

    private function profiles(int $id, array $data): void
    {
        $db = DB::connection('ops');
        $type = $data['type_code'];
        foreach (['TERMINAL' => 'terminals', 'ZONE' => 'zones', 'COUNTER' => 'counters', 'BELT' => 'belts', 'TAXIWAY' => 'taxiways'] as $code => $suffix) {
            if ($type !== $code) {
                continue;
            }
            abort_unless(Schema::connection('ops')->hasTable('infrastructure_'.$suffix), 409, 'Cần cập nhật cấu trúc thuộc tính hạ tầng.');
            $values = array_intersect_key($data, array_flip(InfrastructureCatalog::PROFILE_FIELDS[$type]));
            $db->table('infrastructure_'.$suffix)->updateOrInsert(['infrastructure_id' => $id], $values + ['infrastructure_id' => $id]);
        }
        if ($type === 'GATE') {
            $db->table('infrastructure_gates')->updateOrInsert(['infrastructure_id' => $id], array_intersect_key($data, array_flip(['serves_departures', 'serves_arrivals'])) + ['infrastructure_id' => $id]);
        }
        if ($type === 'RUNWAY') {
            $db->table('infrastructure_runways')->updateOrInsert(['infrastructure_id' => $id], array_intersect_key($data, array_flip(['length_m', 'width_m', 'limitations'])) + ['infrastructure_id' => $id]);
            if (array_key_exists('directions', $data)) {
                $db->table('runway_directions')->where('runway_id', $id)->whereNotIn('designator', $data['directions'])->update(['active' => false, 'updated_at' => now()]);
                foreach ($data['directions'] as $direction) {
                    $existing = $db->table('runway_directions')->where('runway_id', $id)->where('designator', $direction)->first();
                    $db->table('runway_directions')->updateOrInsert(['runway_id' => $id, 'designator' => $direction], ['active' => true, 'updated_at' => now()] + ($existing ? [] : ['created_at' => now()]));
                }
            }
        }
        if ($type === 'STAND') {
            $db->table('infrastructure_stands')->updateOrInsert(['infrastructure_id' => $id], array_intersect_key($data, ['compatibility_notes' => true]) + ['infrastructure_id' => $id]);
        }
        if ($type === 'JET_BRIDGE') {
            $db->table('infrastructure_assets')->updateOrInsert(['infrastructure_id' => $id], array_intersect_key($data, ['asset_reference' => true]) + ['infrastructure_id' => $id]);
        }
        foreach (['stand_ids' => $type === 'GATE' ? 'GATE_STAND' : 'JET_BRIDGE_STAND', 'gate_ids' => 'JET_BRIDGE_GATE'] as $field => $relation) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            // Association changes are versioned in master_changes/audit; physical IDs are retained.
            $db->table('infrastructure_links')->where('source_id', $id)->where('relation', $relation)->whereNotIn('target_id', $data[$field])->delete();
            foreach ($data[$field] as $target) {
                if (! $db->table('infrastructure_links')->where('source_id', $id)->where('target_id', $target)->where('relation', $relation)->exists()) {
                    $db->table('infrastructure_links')->insert(['airport_code' => $data['airport_code'], 'source_id' => $id, 'target_id' => $target, 'relation' => $relation, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    private function recordSource(int $id, User $user, array $data, string $method): void
    {
        DB::connection('ops')->table('infrastructure_sources')->insert(['infrastructure_id' => $id, 'entry_method' => $method, 'source_reference' => $data['source_reference'] ?? null, 'recorded_by' => $user->id, 'created_at' => now()]);
    }

    public function assertAllocatable(object $resource): void
    {
        abort_unless($resource->active && in_array($resource->type, InfrastructureCatalog::SCHEDULED_TYPES, true), 409, 'Tài nguyên không phù hợp để phân bổ.');
        if ($resource->infrastructure_id) {
            $row = DB::connection('ops')->table('airport_infrastructure')->find($resource->infrastructure_id);
            abort_unless($row && $row->active && $row->airport_code === $resource->airport_code && $row->type_code === $resource->type
                && ! in_array($row->technical_state, ['MAINTENANCE', 'OUT_OF_SERVICE'], true), 409, 'Hạ tầng đang ngừng sử dụng hoặc không còn phù hợp.');
        }
    }

    public function recordFixtureUse(object $resource, User $actor, string $purpose): void
    {
        if ($resource->infrastructure_id && DB::connection('ops')->table('infrastructure_sources')->where('infrastructure_id', $resource->infrastructure_id)->where('entry_method', 'LOCAL_FIXTURE')->exists()) {
            Audit::record('infrastructure.fixture.used', (string) $resource->infrastructure_id, null, $purpose, $actor->id, 'ops');
        }
    }
}
