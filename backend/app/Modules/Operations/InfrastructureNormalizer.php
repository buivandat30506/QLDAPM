<?php

namespace App\Modules\Operations;

use App\Support\InfrastructureCatalog;
use Illuminate\Support\Facades\DB;

class InfrastructureNormalizer
{
    public function inspect(): array
    {
        $db = DB::connection('ops');
        $keys = [];
        $conflicts = [];
        $pending = 0;
        foreach (['airport_resources', 'airport_areas'] as $table) {
            foreach ($db->table($table)->orderBy('id')->get() as $row) {
                $key = $row->airport_code.':'.mb_strtolower(rtrim($row->code));
                if (isset($keys[$key]) && (! $row->infrastructure_id || $keys[$key]['infrastructure_id'] !== $row->infrastructure_id)) {
                    $conflicts[] = ['code' => $key, 'sources' => [$keys[$key]['source'], $table.':'.$row->id]];
                }
                $keys[$key] = ['source' => $table.':'.$row->id, 'infrastructure_id' => $row->infrastructure_id];
                if (! isset(InfrastructureCatalog::types()[$row->type])) {
                    $conflicts[] = ['unsupported_type' => $row->type, 'source' => $table.':'.$row->id];
                }
                if (! $row->infrastructure_id) {
                    $pending++;
                }
                if ($row->infrastructure_id && ! $db->table('airport_infrastructure')->where('id', $row->infrastructure_id)->where('airport_code', $row->airport_code)->where('code', $row->code)->where('type_code', $row->type)->exists()) {
                    $conflicts[] = ['invalid_mapping' => $table.':'.$row->id];
                }
                if ($table === 'airport_areas') {
                    $seen = [$row->id];
                    $parentId = $row->parent_id;
                    while ($parentId) {
                        $parent = $db->table('airport_areas')->find($parentId);
                        if (! $parent || $parent->airport_code !== $row->airport_code || in_array($parentId, $seen)) {
                            $conflicts[] = ['invalid_parent' => $row->id];
                            break;
                        }
                        $seen[] = $parentId;
                        $parentId = $parent->parent_id;
                    }
                }
            }
        }

        return ['pending' => $pending, 'conflicts' => $conflicts];
    }

    public function apply(): array
    {
        return DB::connection('ops')->transaction(function () {
            $db = DB::connection('ops');
            $db->table('airports')->orderBy('code')->lockForUpdate()->get();
            $report = $this->inspect();
            abort_if($report['conflicts'], 409, 'Ánh xạ chưa rõ; không tự gộp nguồn cũ.');
            $importedAreas = [];
            foreach (['airport_resources', 'airport_areas'] as $table) {
                foreach ($db->table($table)->whereNull('infrastructure_id')->orderBy('id')->lockForUpdate()->get() as $row) {
                    abort_if($db->table('airport_infrastructure')->where('airport_code', $row->airport_code)->where('code', $row->code)->exists(), 409, 'Mã chuẩn đã tồn tại; cần xác minh ánh xạ.');
                    // Preserve verified source names only. InfrastructureDisplay derives labels at read time.
                    $id = $db->table('airport_infrastructure')->insertGetId(['airport_code' => $row->airport_code, 'code' => $row->code, 'name' => $table === 'airport_areas' ? $row->name : null, 'type_code' => $row->type, 'active' => $row->active, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                    $db->table($table)->where('id', $row->id)->update(['infrastructure_id' => $id]);
                    if ($table === 'airport_areas') {
                        $importedAreas[] = $row->id;
                    }
                    $profiles = ['GATE' => 'gates', 'STAND' => 'stands', 'BELT' => 'belts'];
                    if (isset($profiles[$row->type])) {
                        $db->table('infrastructure_'.$profiles[$row->type])->insert(['infrastructure_id' => $id]);
                    }
                }
            }
            foreach ($db->table('airport_areas')->whereIn('id', $importedAreas)->whereNotNull('parent_id')->get() as $row) {
                $parentId = $db->table('airport_areas')->where('id', $row->parent_id)->value('infrastructure_id');
                $db->table('airport_infrastructure')->where('id', $row->infrastructure_id)->update(['parent_id' => $parentId]);
            }
            foreach ($db->table('airport_areas')->whereIn('id', $importedAreas)->get() as $row) {
                $record = $db->table('airport_infrastructure')->find($row->infrastructure_id);
                app(InfrastructureService::class)->validate((array) $record, $record);
            }

            return $report + ['canonical_records' => $db->table('airport_infrastructure')->count()];
        }, 3);
    }
}
