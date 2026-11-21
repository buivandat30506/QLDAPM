<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InfrastructureCompatibility
{
    public function ready(): bool
    {
        return Schema::connection('ops')->hasTable('airport_infrastructure');
    }

    public function resourceCreated(int $id, ?User $user = null): void
    {
        if (! $this->ready()) {
            return;
        }
        $db = DB::connection('ops');
        $row = $db->table('airport_resources')->find($id);
        abort_if($db->table('airport_infrastructure')->where('airport_code', $row->airport_code)->where('code', $row->code)->exists() || $db->table('airport_areas')->where('airport_code', $row->airport_code)->where('code', $row->code)->exists(), 409, 'Mã hạ tầng đã tồn tại ở nguồn khác.');
        $canonicalId = $db->table('airport_infrastructure')->insertGetId(['airport_code' => $row->airport_code, 'code' => $row->code, 'type_code' => $row->type, 'active' => $row->active, 'technical_state' => 'NORMAL', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $db->table('airport_resources')->where('id', $id)->update(['infrastructure_id' => $canonicalId]);
        if ($row->type === 'GATE') {
            $db->table('infrastructure_gates')->insert(['infrastructure_id' => $canonicalId]);
        }
        if ($row->type === 'STAND') {
            $db->table('infrastructure_stands')->insert(['infrastructure_id' => $canonicalId]);
        }
        if ($user) {
            $this->source($canonicalId, $user);
        }
    }

    public function resourceActive(int $id, bool $active, ?User $user = null): void
    {
        if (! $this->ready()) {
            return;
        }
        $db = DB::connection('ops');
        $infraId = $db->table('airport_resources')->where('id', $id)->value('infrastructure_id');
        if ($infraId) {
            $before = app(InfrastructureService::class)->snapshot((int) $infraId);
            $db->table('airport_infrastructure')->where('id', $infraId)->update(['active' => $active, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            Audit::record('infrastructure.updated', (string) $infraId, json_encode($before), json_encode(app(InfrastructureService::class)->snapshot((int) $infraId)), $user?->id, 'ops');
        }
    }

    public function areaWritten(int $id, ?User $user = null): void
    {
        if (! $this->ready()) {
            return;
        }
        $db = DB::connection('ops');
        $row = $db->table('airport_areas')->find($id);
        $parent = null;
        if ($row->parent_id) {
            $seen = [$id];
            $next = $row->parent_id;
            while ($next) {
                abort_if(in_array($next, $seen), 422, 'Quan hệ khu vực tạo chu trình.');
                $seen[] = $next;
                $ancestor = $db->table('airport_areas')->where('id', $next)->where('airport_code', $row->airport_code)->first();
                abort_unless($ancestor, 422, 'Khu vực cha phải cùng sân bay.');
                $next = $ancestor->parent_id;
            }
            $parent = $db->table('airport_areas')->where('id', $row->parent_id)->value('infrastructure_id');
            abort_unless($parent, 409, 'Cần chuẩn hóa khu vực cha trước.');
        }
        $conflict = $db->table('airport_infrastructure')->where('airport_code', $row->airport_code)->where('code', $row->code);
        if ($row->infrastructure_id) {
            $before = app(InfrastructureService::class)->snapshot((int) $row->infrastructure_id);
            $conflict->where('id', '!=', $row->infrastructure_id);
        }
        abort_if($conflict->exists() || $db->table('airport_resources')->where('airport_code', $row->airport_code)->where('code', $row->code)->exists(), 409, 'Mã hạ tầng đã tồn tại ở nguồn khác.');
        $data = ['airport_code' => $row->airport_code, 'code' => $row->code, 'name' => $row->name, 'type_code' => $row->type, 'parent_id' => $parent, 'active' => $row->active, 'updated_at' => now()];
        if ($row->infrastructure_id) {
            $old = $db->table('airport_infrastructure')->where('id', $row->infrastructure_id)->lockForUpdate()->first();
            abort_unless($old->airport_code === $row->airport_code && $old->code === $row->code && $old->type_code === $row->type, 422, 'Không đổi định danh đã chuẩn hóa.');
            // Validate the canonical graph too, including parents moved through M01.
            app(InfrastructureService::class)->validate($data + ['technical_state' => $old->technical_state, 'description' => $old->description], $old);
            $db->table('airport_infrastructure')->where('id', $row->infrastructure_id)->update($data + ['version' => $old->version + 1]);
            Audit::record('infrastructure.updated', (string) $row->infrastructure_id, json_encode($before), json_encode(app(InfrastructureService::class)->snapshot((int) $row->infrastructure_id)), $user?->id, 'ops');
        } else {
            $data['technical_state'] = 'NORMAL';
            app(InfrastructureService::class)->validate($data, null, ['table' => 'airport_areas', 'id' => $id]);
            $canonicalId = $db->table('airport_infrastructure')->insertGetId($data + ['version' => 1, 'created_at' => now()]);
            $db->table('airport_areas')->where('id', $id)->update(['infrastructure_id' => $canonicalId]);
            Audit::record('infrastructure.created', (string) $canonicalId, null, json_encode(app(InfrastructureService::class)->snapshot((int) $canonicalId)), $user?->id, 'ops');
        }
        if ($user) {
            $this->source((int) ($row->infrastructure_id ?: $canonicalId), $user);
        }
    }

    private function source(int $id, User $user): void
    {
        if (Schema::connection('ops')->hasTable('infrastructure_sources')) {
            DB::connection('ops')->table('infrastructure_sources')->insert(['infrastructure_id'=>$id, 'entry_method'=>'COMPATIBILITY_ENTRY', 'source_reference'=>null, 'recorded_by'=>$user->id, 'created_at'=>now()]);
        }
    }

    public function assertAreaIdentity(object $old, array $data): void
    {
        if ($this->ready() && $old->infrastructure_id) {
            abort_unless($old->airport_code === $data['airport_code'] && $old->code === $data['code'] && $old->type === $data['type'], 422, 'Không đổi định danh khu vực đã liên kết.');
        }
    }
}
