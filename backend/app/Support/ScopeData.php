<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class ScopeData
{
    public const CORE = [
        'ops' => ['airports', 'airport_resources', 'airport_areas', 'airport_infrastructure', 'infrastructure_types', 'infrastructure_assets', 'infrastructure_belts', 'infrastructure_counters', 'infrastructure_gates', 'infrastructure_links', 'infrastructure_runways', 'infrastructure_sources', 'infrastructure_stands', 'infrastructure_taxiways', 'infrastructure_terminals', 'infrastructure_zones', 'runway_directions', 'routes', 'flights', 'flight_seasons', 'flight_season_occurrences', 'flight_schedule_changes', 'flight_actual_events', 'master_changes', 'flight_changes', 'change_reservations', 'disruptions'],
        'mysql' => ['users', 'user_roles', 'airport_status_authorities', 'sessions', 'password_reset_tokens', 'email_verifications', 'inventory', 'fare_classes', 'fare_rule_versions', 'bookings', 'booking_changes', 'reservations', 'reservation_segments', 'reservation_passengers', 'reservation_ancillaries', 'checkout_sessions', 'tickets', 'boarding_passes', 'notifications', 'reservation_ancillaries'],
        'finance' => ['checkout_intents', 'payments', 'refunds', 'ledger_entries'],
    ];

    public const DEPENDENCIES = [
        'ops' => ['aircraft', 'aircraft_models', 'crew_members', 'crew_assignments', 'crew_qualifications', 'crew_availability', 'crew_rosters', 'work_orders', 'work_order_items', 'work_order_materials', 'maintenance_items', 'maintenance_materials', 'ground_tasks', 'ground_services', 'ground_equipment', 'ground_task_services', 'ground_task_equipment', 'ground_task_handoffs', 'resource_allocations', 'integration_connections'],
        'mysql' => ['aircraft_change_receipts', 'baggage', 'baggage_events', 'baggage_allowances', 'baggage_incidents', 'boarding_manifests', 'exchange_requests', 'recovery_offers', 'seat_reassignments', 'passenger_assistance_requests', 'passenger_document_verifications', 'passenger_revisions', 'notification_templates', 'notification_deliveries', 'system_configurations'],
        'finance' => ['fee_catalog'],
    ];

    public static function classification(string $node, string $table): array
    {
        if (in_array($table, self::CORE[$node], true)) {
            return ['KEEP_CORE', 'Nghiệp vụ M01–M03, đặt chuyến hoặc xác thực.'];
        }
        if (in_array($table, self::DEPENDENCIES[$node], true)) {
            return ['KEEP_DEPENDENCY', 'Kiểm tra khai thác, tài nguyên, hành khách hoặc giao dịch phụ thuộc.'];
        }
        if (in_array($table, ['audit_logs', 'inbox', 'outbox', 'migrations', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks', 'demo_installations', 'm02_demo_sets', 'infrastructure_fixture_sets', 'infrastructure_simplification_archive', 'devices'], true)) {
            return ['KEEP_COMPATIBILITY', 'Giữ audit, định danh, đồng bộ, cấu hình và khả năng khôi phục.'];
        }
        if ($node === 'ops' && $table === 'telemetry') {
            return ['REMOVE_SAFE', 'Chỉ đo lường IoT cũ quá 30 phút, không có khóa ngoại trỏ vào; không phải nhật ký điều hành. Bản ghi mới được giữ.'];
        }

        return ['NEEDS_REVIEW', 'Chưa đủ căn cứ xóa lịch sử hỗ trợ, phân tích hoặc tài chính độc lập; giữ nguyên.'];
    }

    public static function fingerprint(object|array $row): string
    {
        $row = (array) $row;
        ksort($row);

        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function inventory(): array
    {
        $result = ['created_at' => now()->toIso8601String(), 'nodes' => []];
        foreach (['ops', 'mysql', 'finance'] as $node) {
            $db = DB::connection($node);
            $name = $db->getDatabaseName();
            $meta = (array) $db->selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@server_uuid AS uuid');
            $meta['foreign_keys'] = $db->select('SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION', [$name]);
            $meta['views'] = $db->select('SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?', [$name]);
            $meta['triggers'] = $db->select('SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$name]);
            $meta['routines'] = $db->select('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?', [$name]);
            $meta['tables'] = [];
            foreach ($db->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$name, 'BASE TABLE']) as $t) {
                $table = $t->TABLE_NAME;
                $pk = array_map(fn ($x) => $x->COLUMN_NAME, $db->select("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION", [$name, $table]));
                $q = $db->table($table);
                foreach ($pk ?: $db->getSchemaBuilder()->getColumnListing($table) as $column) {
                    $q->orderBy($column);
                }
                $hash = hash_init('sha256');
                $idHash = hash_init('sha256');
                $count = 0;
                foreach ($q->cursor() as $row) {
                    hash_update($hash, self::fingerprint($row)."\n");
                    hash_update($idHash, json_encode(array_intersect_key((array) $row, array_flip($pk)), JSON_THROW_ON_ERROR)."\n");
                    $count++;
                }
                [$classification,$reason] = self::classification($node, $table);
                $meta['tables'][$table] = compact('count', 'pk', 'classification', 'reason') + ['sha256' => hash_final($hash), 'ids_sha256' => hash_final($idHash)];
            }
            $meta['fk_orphans'] = [];
            foreach (collect($meta['foreign_keys'])->groupBy(fn ($f) => $f->TABLE_NAME.'.'.$f->CONSTRAINT_NAME) as $key => $cols) {
                $first = $cols->first();
                $q = $db->table($first->TABLE_NAME.' as child');
                $q->whereNotExists(function ($sub) use ($cols, $first) {
                    $sub->selectRaw('1')->from($first->REFERENCED_TABLE_NAME.' as parent');
                    foreach ($cols as $f) {
                        $sub->whereColumn('parent.'.$f->REFERENCED_COLUMN_NAME, 'child.'.$f->COLUMN_NAME);
                    }
                });
                foreach ($cols as $f) {
                    $q->whereNotNull('child.'.$f->COLUMN_NAME);
                }
                $meta['fk_orphans'][$key] = $q->count();
            }
            $result['nodes'][$node] = $meta;
        }
        $result['cross_node_orphans'] = [];
        foreach ([['mysql', 'inventory', 'flight_id', 'ops', 'flights', 'id'], ['mysql', 'bookings', 'flight_id', 'ops', 'flights', 'id'], ['mysql', 'fare_classes', 'flight_id', 'ops', 'flights', 'id'], ['finance', 'payments', 'booking_id', 'mysql', 'bookings', 'id'], ['finance', 'checkout_intents', 'booking_id', 'mysql', 'bookings', 'id']] as [$child,$table,$column,$parent,$target,$pk]) {
            $ids = DB::connection($parent)->table($target)->pluck($pk)->all();
            $result['cross_node_orphans']["$child.$table.$column"] = DB::connection($child)->table($table)->whereNotNull($column)->whereNotIn($column, $ids)->count();
        }
        $result['flight_states'] = DB::connection('ops')->table('flights')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();

        return $result;
    }

    public static function plan(): array
    {
        $db = DB::connection('ops');
        $cutoff = now()->subMinutes(30)->toDateTimeString();
        $rows = $db->table('telemetry')->where('received_at', '<', $cutoff)->orderBy('id')->get();
        // Refuse deletion if the schema acquired a new dependent or trigger.
        $incoming = $db->select("SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'telemetry'");
        $triggers = $db->select("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'telemetry'");
        if ($incoming || $triggers) {
            throw new \RuntimeException('Telemetry acquired a dependency; review required.');
        }

        return ['version' => 1, 'cutoff' => $cutoff, 'topology' => (array) $db->selectOne('SELECT @@server_uuid AS uuid, @@hostname AS host'), 'table' => 'telemetry', 'classification' => 'REMOVE_SAFE', 'rows' => $rows->map(fn ($r) => ['id' => $r->id, 'sha256' => self::fingerprint($r)])->all()];
    }

    public static function apply(array $plan): int
    {
        $db = DB::connection('ops');
        if (! app()->environment('local', 'testing') || ! in_array($db->getDatabaseName(), ['airline', 'airline_scope_copy'], true) || config('database.connections.ops.host') !== 'db1') {
            throw new \RuntimeException('Not an authorized local database.');
        }
        if (($plan['table'] ?? '') !== 'telemetry' || ($plan['classification'] ?? '') !== 'REMOVE_SAFE' || ($plan['version'] ?? 0) !== 1) {
            throw new \RuntimeException('Unsupported plan.');
        }
        $current = self::plan();
        if ($current['topology'] !== $plan['topology']) {
            throw new \RuntimeException('Topology changed.');
        }

        return $db->transaction(function () use ($db, $plan) {
            $count = 0;
            foreach ($plan['rows'] as $entry) {
                $row = $db->table('telemetry')->where('id', $entry['id'])->lockForUpdate()->first();
                if (! $row) {
                    continue;
                } // Controlled replay.
                if (self::fingerprint($row) !== $entry['sha256'] || $row->received_at >= $plan['cutoff']) {
                    throw new \RuntimeException('Candidate changed; regenerate and rehearse the plan.');
                }
                $count += $db->table('telemetry')->where('id', $entry['id'])->delete();
            }

            return $count;
        });
    }
}
