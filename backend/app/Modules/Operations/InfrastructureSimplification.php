<?php

namespace App\Modules\Operations;

use App\Modules\Shared\Audit;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureLegacyMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InfrastructureSimplification
{
    public const NEW_PROFILES = ['terminals', 'zones', 'counters', 'taxiways'];

    public function preflight(): array
    {
        $db = DB::connection('ops');
        $retired = InfrastructureLegacyMap::retired();
        $rows = $db->table('airport_infrastructure')->whereIn('type_code', $retired)->orderBy('id')->get();
        $blocked = [];
        if ($db->table('infrastructure_types')->whereNotIn('code', array_column(InfrastructureLegacyMap::archivedTypes(), 'code'))->exists()) {
            $blocked[] = 'Có mã loại ngoài danh mục gốc; cần rà soát trước khi tinh gọn.';
        }
        foreach ($rows as $row) {
            if (! isset(InfrastructureLegacyMap::CONVERSIONS[$row->type_code])) {
                $blocked[] = 'Chưa có ánh xạ chính xác: '.$row->type_code.' #'.$row->id;

                continue;
            }
            if ($db->table('master_changes')->where('entity', 'airport_infrastructure')->where('record_id', (string) $row->id)->where('status', 'PENDING')->exists()) {
                $blocked[] = 'Đề nghị chờ duyệt cần xử lý trước: #'.$row->id;
            }
            foreach (['airport_resources', 'airport_areas'] as $table) {
                if ($db->table($table)->where('airport_code', $row->airport_code)->where('code', $row->code)->where(fn ($q) => $q->whereNull('infrastructure_id')->orWhere('infrastructure_id', '!=', $row->id))->exists()) {
                    $blocked[] = 'Nguồn chưa ánh xạ rõ: '.$table.' '.$row->code;
                }
            }
            if (in_array($row->type_code, ['ARRIVAL_BELT', 'DEPARTURE_BELT'], true)) {
                $flow = $db->table('infrastructure_belts')->where('infrastructure_id', $row->id)->value('baggage_flow');
                $expected = InfrastructureLegacyMap::CONVERSIONS[$row->type_code][2]['baggage_flow'];
                if ($flow !== null && $flow !== $expected) {
                    $blocked[] = 'Hướng băng chuyền mâu thuẫn: #'.$row->id;
                }
            }
        }
        foreach (['airport_resources', 'airport_areas'] as $table) {
            foreach ($db->table($table)->whereIn('type', $retired)->get() as $legacy) {
                if (! $legacy->infrastructure_id || ! $rows->contains('id', $legacy->infrastructure_id)) {
                    $blocked[] = 'Nguồn loại cũ chưa chuẩn hóa: '.$table.' #'.$legacy->id;
                } elseif ($rows->firstWhere('id', $legacy->infrastructure_id)->type_code !== $legacy->type) {
                    $blocked[] = 'Phân loại nguồn không khớp hồ sơ: '.$table.' #'.$legacy->id;
                }
            }
        }
        $hasRetiredType = function ($value) use (&$hasRetiredType, $retired): bool {
            if (! is_array($value)) {
                return false;
            }
            foreach ($value as $key => $item) {
                if (in_array($key, ['type', 'type_code'], true) && is_string($item) && in_array($item, $retired, true)) {
                    return true;
                }
                if ($hasRetiredType($item)) {
                    return true;
                }
            }

            return false;
        };
        foreach ($db->table('master_changes')->where('status', 'PENDING')->cursor() as $proposal) {
            if ($hasRetiredType(json_decode($proposal->before_data ?? '', true)) || $hasRetiredType(json_decode($proposal->after_data ?? '', true))) {
                $blocked[] = 'Đề nghị chờ duyệt còn phân loại cũ: #'.$proposal->id;
            }
        }
        $database = $db->getDatabaseName();
        foreach ($db->select('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME=?', [$database, $database, 'infrastructure_types']) as $fk) {
            if ($fk->TABLE_NAME === 'airport_infrastructure' && $fk->COLUMN_NAME === 'type_code') {
                continue;
            }
            if ($db->table($fk->TABLE_NAME)->whereIn($fk->COLUMN_NAME, $retired)->exists()) {
                $blocked[] = 'Khóa ngoại còn sử dụng loại cũ: '.$fk->TABLE_NAME.'.'.$fk->COLUMN_NAME;
            }
        }
        if ($blocked) {
            throw new RuntimeException(implode("\n", array_unique($blocked)));
        }

        return $rows->all();
    }

    public function fingerprint(): string
    {
        $db = DB::connection('ops');
        $hash = hash_init('sha256');
        $tables = ['airport_infrastructure', 'airport_resources', 'airport_areas', 'resource_allocations', 'master_changes', 'ground_equipment', 'infrastructure_types', 'infrastructure_links', 'runway_directions'];
        foreach (array_unique(array_values(InfrastructureCatalog::PROFILE_TABLES)) as $suffix) {
            $tables[] = 'infrastructure_'.$suffix;
        }
        foreach ($tables as $table) {
            hash_update($hash, $table);
            $key = Schema::connection('ops')->getColumnListing($table)[0];
            foreach ($db->table($table)->orderBy($key)->cursor() as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
            }
        }

        return hash_final($hash);
    }

    public function apply(): void
    {
        $db = DB::connection('ops');
        $db->transaction(function () use ($db) {
            $db->table('airports')->orderBy('code')->lockForUpdate()->get();
            if ($db->table('infrastructure_simplification_archive')->where('id', 1)->exists()) {
                throw new RuntimeException('Simplification archive already exists; do not overwrite it.');
            }
            $rows = $this->preflight();
            $payload = ['types' => $db->table('infrastructure_types')->orderBy('code')->get()->all(), 'canonical' => [], 'resources' => [], 'areas' => [], 'new_resource_ids' => [], 'belts' => []];
            foreach (InfrastructureCatalog::types() as $row) {
                $db->table('infrastructure_types')->updateOrInsert(['code' => $row['code']], $row);
            }
            foreach ($rows as $row) {
                [$target,$profile,$attributes] = InfrastructureLegacyMap::CONVERSIONS[$row->type_code];
                $payload['canonical'][] = $row;
                foreach (['airport_resources' => 'resources', 'airport_areas' => 'areas'] as $table => $key) {
                    foreach ($db->table($table)->where('infrastructure_id', $row->id)->get() as $legacy) {
                        $payload[$key][] = $legacy;
                        $db->table($table)->where('id', $legacy->id)->update(['type' => $target]);
                    }
                }
                if ($profile === 'belts') {
                    $payload['belts'][] = ['id' => $row->id, 'before' => $db->table('infrastructure_belts')->where('infrastructure_id', $row->id)->first()];
                }
                $db->table('airport_infrastructure')->where('id', $row->id)->update(['type_code' => $target]);
                $db->table('infrastructure_'.$profile)->updateOrInsert(['infrastructure_id' => $row->id], $attributes);
                if (in_array($target, InfrastructureCatalog::SCHEDULED_TYPES, true) && ! $db->table('airport_resources')->where('infrastructure_id', $row->id)->exists()) {
                    $payload['new_resource_ids'][] = $db->table('airport_resources')->insertGetId(['airport_code' => $row->airport_code, 'code' => $row->code, 'type' => $target, 'active' => $row->active, 'infrastructure_id' => $row->id]);
                }
                Audit::record('infrastructure.classification.normalized', (string) $row->id, json_encode(['type_code' => $row->type_code], JSON_THROW_ON_ERROR), json_encode(['type_code' => $target, 'attributes' => $attributes], JSON_THROW_ON_ERROR), null, 'ops');
            }
            $db->table('infrastructure_types')->whereNotIn('code', array_keys(InfrastructureCatalog::LABELS))->delete();
            if ($db->table('infrastructure_types')->count() !== 10) {
                throw new RuntimeException('Official catalogue must contain exactly ten types.');
            }
            $db->table('infrastructure_simplification_archive')->insert(['id' => 1, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'after_hash' => $this->fingerprint(), 'created_at' => now()]);
        }, 3);
    }

    public function rollback(): void
    {
        $db = DB::connection('ops');
        $db->transaction(function () use ($db) {
            $db->table('airports')->orderBy('code')->lockForUpdate()->get();
            $archive = $db->table('infrastructure_simplification_archive')->where('id', 1)->lockForUpdate()->first();
            if (! $archive || ! hash_equals($archive->after_hash, $this->fingerprint())) {
                throw new RuntimeException('Rollback refused: infrastructure/allocations/proposals changed or archive is missing.');
            }
            $payload = json_decode($archive->payload, true, flags: JSON_THROW_ON_ERROR);
            foreach ($payload['types'] as $row) {
                $db->table('infrastructure_types')->updateOrInsert(['code' => $row['code']], $row);
            }
            $db->table('airport_resources')->whereIn('id', $payload['new_resource_ids'])->delete();
            foreach ($payload['canonical'] as $row) {
                $db->table('airport_infrastructure')->where('id', $row['id'])->update(['type_code' => $row['type_code']]);
            }
            foreach (['resources' => 'airport_resources', 'areas' => 'airport_areas'] as $key => $table) {
                foreach ($payload[$key] as $row) {
                    $db->table($table)->where('id', $row['id'])->update(['type' => $row['type']]);
                }
            }
            foreach ($payload['belts'] as $row) {
                if ($row['before']) {
                    $db->table('infrastructure_belts')->where('infrastructure_id', $row['id'])->update($row['before']);
                } else {
                    $db->table('infrastructure_belts')->where('infrastructure_id', $row['id'])->delete();
                }
            }
        }, 3);
    }
}
