<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Explicit local copy transformation. Never applies to the original airline schema. */
final class CorePruning
{
    public const TARGET = 'airline_core_20261113';

    public const DROP = [
        'ops' => ['telemetry', 'devices', 'aircraft_models', 'crew_qualifications', 'crew_availability', 'crew_rosters', 'work_order_items', 'work_order_materials', 'maintenance_items', 'maintenance_materials', 'ground_task_equipment', 'ground_task_handoffs', 'ground_task_services', 'ground_equipment', 'ground_services', 'integration_connections'],
        'mysql' => ['analytics_dataset_records', 'analytics_recommendations', 'analytics_model_runs', 'analytics_datasets', 'support_compensations', 'support_messages', 'customer_interactions', 'support_cases', 'notification_deliveries', 'notification_templates', 'system_configurations', 'baggage_incidents', 'baggage_allowances', 'fare_rule_versions'],
        'finance' => ['reconciliation_adjustments', 'reconciliation_imports', 'financial_adjustments', 'statement_rows', 'statement_batches', 'reconciliation_entries', 'reconciliation_runs', 'reconciliation_reason_codes', 'fee_catalog'],
    ];

    public static function guard(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Local/testing only');
        }
        foreach (['ops' => 'db1', 'mysql' => 'db2', 'finance' => 'db3'] as $node => $host) {
            if (DB::connection($node)->getDatabaseName() !== self::TARGET || config("database.connections.$node.host") !== $host) {
                throw new \RuntimeException('Refusing to prune any schema except the dedicated local core copy');
            }
        }
    }

    public static function inventory(): array
    {
        $data = ScopeData::inventory();
        foreach ($data['nodes'] as $node => &$meta) {
            $db = DB::connection($node);
            // FILE_SIZE/ALLOCATED_SIZE are actual tablespace sizes, not TABLE_ROWS/DATA_LENGTH estimates.
            $spaces = collect($db->select('SELECT NAME, FILE_SIZE, ALLOCATED_SIZE FROM information_schema.INNODB_TABLESPACES WHERE SUBSTRING_INDEX(NAME, ?, 1) = ?', ['/', $db->getDatabaseName()]))->keyBy(fn ($r) => substr($r->NAME, strpos($r->NAME, '/') + 1));
            foreach ($meta['tables'] as $table => &$info) {
                $space = $spaces->get($table) ?? throw new \RuntimeException('Missing physical tablespace: '.$table);
                $info['file_bytes'] = (int) $space->FILE_SIZE;
                $info['allocated_bytes'] = (int) $space->ALLOCATED_SIZE;
                if (in_array($table, self::DROP[$node], true)) {
                    $info['classification'] = 'REMOVABLE';
                    $info['reason'] = match (true) {
                        str_starts_with($table, 'analytics_'), in_array($table, ['devices', 'telemetry']) => 'AI/IoT độc lập; CLI IoT bị ngừng, không có consumer cốt lõi.',
                        str_starts_with($table, 'support_'), $table === 'customer_interactions' => 'CRM độc lập; bỏ tác vụ escalation khỏi worker, recovery không dùng CRM.',
                        $node === 'finance' => 'Đối soát/báo cáo/cấu hình phí riêng; checkout, refund và ledger không đọc các bảng này.',
                        default => 'Danh mục/phần mở rộng chỉ được use case và màn hình đã ngừng dùng; giữ kiểm tra nguồn lực trên hồ sơ chính.',
                    };
                } else {
                    $info['classification'] = $info['classification'] === 'KEEP_CORE' ? 'CORE' : 'REQUIRED_DEPENDENCY';
                    if (in_array($table, ['bookings', 'checkout_sessions', 'boarding_passes', 'tickets', 'baggage', 'baggage_events', 'payments', 'checkout_intents', 'ledger_entries'])) {
                        $info['classification'] = 'CAN_BE_SIMPLIFIED';
                        $info['reason'] = 'Giữ cấu trúc và giao dịch còn dùng; chỉ lưu trữ bớt snapshot demo BOARDED của chuyến ARRIVED có provenance đầy đủ.';
                    }
                }
            }
            unset($info);
        }
        unset($meta);

        foreach ([['finance', 'ledger_entries', 'booking_id', 'mysql', 'bookings', 'id'], ['finance', 'refunds', 'booking_id', 'mysql', 'bookings', 'id'], ['mysql', 'checkout_sessions', 'intent_id', 'finance', 'checkout_intents', 'id'], ['mysql', 'reservation_segments', 'flight_id', 'ops', 'flights', 'id']] as [$child,$table,$column,$parent,$target,$pk]) {
            $ids = DB::connection($parent)->table($target)->pluck($pk)->all();
            $data['cross_node_orphans']["$child.$table.$column"] = DB::connection($child)->table($table)->whereNotNull($column)->whereNotIn($column, $ids)->count();
        }
        $published = DB::connection('ops')->table('flights')->whereNotNull('published_at')->pluck('id')->all();
        $stocks = DB::table('inventory')->whereIn('flight_id', $published)->pluck('flight_id')->all();
        $data['published_without_inventory'] = count(array_diff($published, $stocks));

        return $data;
    }

    public static function dropOrder(string $node, array $tables): array
    {
        $db = DB::connection($node);
        $order = [];
        $keys = $db->select('SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        $keys = array_values(array_filter($keys, function ($key) use ($db, $node) {
            if ($node === 'ops' && $key->TABLE_NAME === 'aircraft' && $key->COLUMN_NAME === 'model_id' && $key->CONSTRAINT_NAME === 'aircraft_model_id_foreign' && $key->REFERENCED_TABLE_NAME === 'aircraft_models') {
                if ($db->table('aircraft')->whereNotNull('model_id')->exists()) {
                    throw new \RuntimeException('Aircraft model linkage contains data; cannot detach');
                }

                return false;
            }

            return true;
        }));
        foreach ($keys as $key) {
            if (in_array($key->REFERENCED_TABLE_NAME, $tables, true) && ($key->TABLE_SCHEMA !== $db->getDatabaseName() || ! in_array($key->TABLE_NAME, $tables, true))) {
                throw new \RuntimeException('A kept table references a proposed drop: '.$key->TABLE_NAME);
            }
        }
        while ($tables) {
            $progress = false;
            foreach ($tables as $i => $table) {
                foreach ($keys as $key) {
                    if ($key->REFERENCED_TABLE_NAME === $table && $key->TABLE_NAME !== $table && in_array($key->TABLE_NAME, $tables, true)) {
                        continue 2;
                    }
                }
                $order[] = $table;
                unset($tables[$i]);
                $progress = true;
            }
            if (! $progress) {
                throw new \RuntimeException('Cycle in drop graph');
            }
        }

        return $order;
    }

    public static function plan(array $inventory): array
    {
        self::guard();
        $plan = ['target' => self::TARGET, 'created_at' => now()->toIso8601String(), 'nodes' => [], 'booking_ids' => [], 'delete' => []];
        foreach (self::DROP as $node => $tables) {
            $meta = $inventory['nodes'][$node];
            if ($meta['db'] !== self::TARGET || $meta['triggers'] || $meta['routines'] || $meta['views']) {
                throw new \RuntimeException('Unexpected database objects');
            }
            $plan['nodes'][$node] = ['uuid' => $meta['uuid'], 'drop_order' => self::dropOrder($node, $tables), 'drops' => array_intersect_key($meta['tables'], array_flip($tables))];
            if (DB::connection($node)->table('outbox')->whereNull('delivered_at')->exists()) {
                throw new \RuntimeException('Pending outbox must be resolved before planning');
            }
        }
        $ops = DB::connection('ops');
        $db = DB::connection('mysql');
        $fin = DB::connection('finance');
        $arrived = $ops->table('flights')->where('status', 'ARRIVED')->whereNotNull('actual_arrival')->pluck('id')->all();
        $snapshots = $ops->table('audit_logs')->where('action', 'demo.flight_snapshot')->pluck('object_id')->all();
        $candidates = $db->table('bookings as b')->join('users as u', 'u.id', '=', 'b.user_id')->whereIn('b.flight_id', $arrived)->where('b.status', 'BOARDED')->whereNull('b.reservation_id')->whereNull('b.passenger_id')->where('b.idempotency_key', 'like', 'demo-manifest-%')->select('b.*', 'u.email')->orderBy('b.id')->get()->filter(function ($b) use ($snapshots) {
            return preg_match('/^demo-manifest-([0-9]+)-([0-9]+)$/D', $b->idempotency_key, $m) && (int) $m[1] === $b->flight_id && ! in_array((int) $m[2], [0, 1, 2, 4, 5, 6, 12, 13, 14], true) && in_array((string) $b->flight_id, $snapshots, true) && preg_match('/^demo\.p[0-9]{4}@airline\.test$/D', $b->email);
        });
        $ids = $candidates->pluck('id')->all();
        $payments = $fin->table('payments')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $ledger = $fin->table('ledger_entries')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $intents = $fin->table('checkout_intents')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $sessions = $db->table('checkout_sessions')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $tickets = $db->table('tickets')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $bags = $db->table('baggage')->whereIn('booking_id', $ids)->get()->groupBy('booking_id');
        $protected = [];
        // Any non-archived booking relation fences the group out of sampling.
        foreach (['mysql', 'finance'] as $node) {
            $d = DB::connection($node);
            foreach ($inventory['nodes'][$node]['tables'] as $table => $info) {
                if (in_array($table, self::DROP[$node], true) || in_array($table, ['bookings', 'tickets', 'boarding_passes', 'checkout_sessions', 'baggage', 'payments', 'ledger_entries', 'checkout_intents'], true)) {
                    continue;
                }
                $columns = $d->getSchemaBuilder()->getColumnListing($table);
                foreach (array_intersect($columns, ['booking_id', 'source_booking_id', 'target_booking_id']) as $column) {
                    foreach ($d->table($table)->whereIn($column, $ids)->pluck($column) as $id) {
                        $protected[$id] = true;
                    }
                }
            }
        }
        foreach ($candidates as $b) {
            if (isset($protected[$b->id])) {
                continue;
            }
            $p = $payments->get($b->id, collect());
            $l = $ledger->get($b->id, collect());
            if ($p->count() !== 1 || $l->count() !== 1) {
                continue;
            }
            $p = $p->first();
            $l = $l->first();
            if ($p->mode !== 'mock' || $p->status !== 'CAPTURED' || (int) $p->amount !== (int) $b->amount || $l->kind !== 'CAPTURE' || (int) $l->amount !== (int) $b->amount) {
                continue;
            }
            if ($intents->get($b->id, collect())->contains(fn ($i) => $i->mode !== 'mock' || $i->status !== 'CAPTURED' || (int) $i->amount !== (int) $b->amount)) {
                continue;
            }
            if ($sessions->get($b->id, collect())->contains(fn ($s) => $s->mode !== 'mock' || $s->status !== 'READY')) {
                continue;
            }
            if ($tickets->get($b->id, collect())->count() !== 1 || ! str_starts_with($tickets[$b->id]->first()->number, 'ASD')) {
                continue;
            }
            if ($bags->get($b->id, collect())->contains(fn ($bag) => ! in_array($bag->status, ['DELIVERED', 'OFFLOADED'], true))) {
                continue;
            }
            $plan['booking_ids'][] = $b->id;
        }
        $ids = $plan['booking_ids'];
        $bagIds = $db->table('baggage')->whereIn('booking_id', $ids)->pluck('id')->all();
        $queries = [
            ['mysql', 'baggage_events', 'baggage_id', $bagIds], ['mysql', 'baggage', 'booking_id', $ids], ['mysql', 'boarding_passes', 'booking_id', $ids], ['mysql', 'tickets', 'booking_id', $ids], ['mysql', 'checkout_sessions', 'booking_id', $ids],
            ['finance', 'checkout_intents', 'booking_id', $ids], ['finance', 'ledger_entries', 'booking_id', $ids], ['finance', 'payments', 'booking_id', $ids], ['mysql', 'bookings', 'id', $ids],
        ];
        foreach ($queries as [$node,$table,$column,$values]) {
            $pk = $inventory['nodes'][$node]['tables'][$table]['pk'];
            $rows = [];
            foreach (DB::connection($node)->table($table)->whereIn($column, $values)->orderBy($pk[0])->cursor() as $row) {
                $rows[] = ['pk' => array_intersect_key((array) $row, array_flip($pk)), 'sha256' => ScopeData::fingerprint($row)];
            }
            $plan['delete'][] = compact('node', 'table', 'rows');
        }
        $plan['preserved'] = [];
        foreach ($inventory['nodes'] as $node => $meta) {
            foreach ($meta['tables'] as $table => $info) {
                if (in_array($table, self::DROP[$node], true)) {
                    continue;
                }
                $set = collect($plan['delete'])->first(fn ($s) => $s['node'] === $node && $s['table'] === $table);
                if (! $set || ! $set['rows']) {
                    $plan['preserved'][$node][$table] = array_intersect_key($info, array_flip(['count', 'sha256', 'ids_sha256']));

                    continue;
                }
                $pk = $info['pk'];
                $query = DB::connection($node)->table($table)->whereNotIn($pk[0], array_column(array_column($set['rows'], 'pk'), $pk[0]));
                foreach ($pk as $key) {
                    $query->orderBy($key);
                }
                $hash = hash_init('sha256');
                $idsHash = hash_init('sha256');
                $count = 0;
                foreach ($query->cursor() as $row) {
                    hash_update($hash, ScopeData::fingerprint($row)."\n");
                    hash_update($idsHash, json_encode(array_intersect_key((array) $row, array_flip($pk)), JSON_THROW_ON_ERROR)."\n");
                    $count++;
                }
                $plan['preserved'][$node][$table] = ['count' => $count, 'sha256' => hash_final($hash), 'ids_sha256' => hash_final($idsHash)];
            }
        }

        return $plan;
    }

    public static function apply(array $plan, array $inventory): array
    {
        self::guard();
        if ($plan['target'] !== self::TARGET) {
            throw new \RuntimeException('Wrong plan target');
        }
        foreach (self::DROP as $node => $tables) {
            if ($inventory['nodes'][$node]['uuid'] !== $plan['nodes'][$node]['uuid']) {
                throw new \RuntimeException('Topology mismatch');
            }
            self::dropOrder($node, $tables);
            foreach ($tables as $table) {
                if ($inventory['nodes'][$node]['tables'][$table]['sha256'] !== $plan['nodes'][$node]['drops'][$table]['sha256']) {
                    throw new \RuntimeException('Drop candidate changed: '.$table);
                }
            }
        }
        // Validate every exact row fingerprint before the first mutation.
        foreach ($plan['delete'] as $set) {
            foreach (array_chunk($set['rows'], 500) as $chunk) {
                $pk = array_key_first($chunk[0]['pk']);
                $actual = DB::connection($set['node'])->table($set['table'])->whereIn($pk, array_column(array_column($chunk, 'pk'), $pk))->get()->keyBy($pk);
                foreach ($chunk as $r) {
                    if (! isset($actual[$r['pk'][$pk]]) || ScopeData::fingerprint($actual[$r['pk'][$pk]]) !== $r['sha256']) {
                        throw new \RuntimeException('Row changed: '.$set['table']);
                    }
                }
            }
        }
        $counts = [];
        // Retire only this unused nullable integration, never FOREIGN_KEY_CHECKS.
        DB::connection('ops')->getSchemaBuilder()->table('aircraft', fn ($t) => $t->dropForeign('aircraft_model_id_foreign'));
        foreach ($plan['delete'] as $set) {
            $d = DB::connection($set['node']);
            $count = 0;
            foreach (array_chunk($set['rows'], 500) as $chunk) {
                $pk = array_key_first($chunk[0]['pk']);
                $count += $d->transaction(fn () => $d->table($set['table'])->whereIn($pk, array_column(array_column($chunk, 'pk'), $pk))->delete());
            }
            $counts[$set['node'].'.'.$set['table']] = $count;
        }
        foreach ($plan['nodes'] as $node => $meta) {
            foreach ($meta['drop_order'] as $table) {
                DB::connection($node)->getSchemaBuilder()->drop($table);
            }
        }
        foreach ($plan['delete'] as $set) {
            if ($set['rows']) {
                DB::connection($set['node'])->select('OPTIMIZE TABLE `'.$set['table'].'`');
            }
        }

        return ['deleted_rows' => $counts, 'dropped_tables' => self::DROP, 'archived_booking_groups' => count($plan['booking_ids'])];
    }
}
