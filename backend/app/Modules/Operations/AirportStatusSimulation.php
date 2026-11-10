<?php

namespace App\Modules\Operations;

use App\Modules\Shared\Audit;
use Illuminate\Support\Facades\DB;

class AirportStatusSimulation
{
    public const KEY = 'airport-status-local-v2';

    public const KEYS = ['airport-status-local-v1', self::KEY];

    public const NOTE = 'Dữ liệu mô phỏng phục vụ kiểm thử và trình bày; không phải thông tin vận hành thực tế.';

    public const FIELDS = ['operational_status', 'operational_status_note', 'operational_status_updated_by', 'operational_status_updated_at', 'operational_status_source', 'operational_status_simulation_key'];

    public function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Dữ liệu mô phỏng chỉ được chạy trong local/testing.');
        abort_unless(in_array(config('database.connections.ops.host'), ['db1', 'localhost', '127.0.0.1', '::1'], true), 403, 'Chỉ cho phép node cơ sở dữ liệu cục bộ.');
    }

    public static function fingerprint(object|array $row): string
    {
        $row = (array) $row;

        return hash('sha256', json_encode(array_map(fn ($field) => $row[$field] ?? null, self::FIELDS), JSON_THROW_ON_ERROR));
    }

    public function targets(int $total): array
    {
        $weights = ['NORMAL' => 15, 'HIGH' => 4, 'OVERLOAD' => 2, 'MAINTENANCE' => 1];
        if ($total < 4) {
            return array_combine(array_keys($weights), array_map(fn ($i) => $i < $total ? 1 : 0, range(0, 3)));
        }
        $counts = array_map(fn ($weight) => max(1, (int) floor($total * $weight / 22)), $weights);
        while (array_sum($counts) !== $total) {
            $add = array_sum($counts) < $total;
            $best = null;
            $score = -INF;
            foreach ($weights as $status => $weight) {
                if (! $add && $counts[$status] <= 1) {
                    continue;
                }
                $difference = $add ? $total * $weight / 22 - $counts[$status] : $counts[$status] - $total * $weight / 22;
                if ($difference > $score) {
                    $best = $status;
                    $score = $difference;
                }
            }
            $counts[$best] += $add ? 1 : -1;
        }

        return $counts;
    }

    private function managed(object $row): bool
    {
        return ($row->operational_status_source ?? null) === 'SIMULATION'
            && in_array($row->operational_status_simulation_key, self::KEYS, true)
            && hash_equals($row->operational_status_simulation_fingerprint ?? '', self::fingerprint($row));
    }

    private function untouched(object $row): bool
    {
        foreach (array_merge(self::FIELDS, ['operational_status_simulation_fingerprint']) as $field) {
            if (($row->$field ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    private function plan($rows, bool $rebalance, ?string $randomSeed = null): array
    {
        $eligible = $rows->filter(fn ($r) => $this->untouched($r) || ($rebalance && $this->managed($r)));
        $targets = $this->targets($rows->count());
        $counts = array_fill_keys(array_keys($targets), 0);
        foreach ($rows as $row) {
            if (! $eligible->contains('code', $row->code) && isset($counts[$row->operational_status])) {
                $counts[$row->operational_status]++;
            }
        }
        $quotas = array_fill_keys(array_keys($targets), 0);
        $remaining = $eligible->count();
        if ($rows->count() >= 4) {
            foreach ($quotas as $status => $_) {
                if (! $counts[$status] && $remaining > 0) {
                    $quotas[$status]++;
                    $remaining--;
                }
            }
        }
        while ($remaining-- > 0) {
            $best = 'NORMAL';
            $score = -INF;
            foreach ($targets as $status => $target) {
                if ($target - $counts[$status] - $quotas[$status] > $score) {
                    $score = $target - $counts[$status] - $quotas[$status];
                    $best = $status;
                }
            }
            $quotas[$best]++;
        }
        $pool = $eligible->keyBy('code');
        if ($randomSeed !== null) {
            $codes = $pool->keys()->sort(fn ($a, $b) => strcmp(hash('sha256', $randomSeed.':'.$a), hash('sha256', $randomSeed.':'.$b)))->values()->all();
            $pool = collect($codes)->mapWithKeys(fn ($code) => [$code => $pool->get($code)]);
        }
        $plan = [];
        foreach (['MAINTENANCE' => ['DAD'], 'OVERLOAD' => ['CXR', 'HAN'], 'HIGH' => ['CAH', 'SGN', 'PQC', 'DLI'], 'NORMAL' => []] as $status => $preferred) {
            if ($randomSeed !== null) {
                $preferred = [];
            }
            $codes = array_unique(array_merge($preferred, $pool->keys()->all()));
            foreach ($codes as $code) {
                if ($quotas[$status] > 0 && $pool->has($code)) {
                    $plan[$code] = $status;
                    $pool->forget($code);
                    $quotas[$status]--;
                }
            }
        }

        return $plan;
    }

    public function run(bool $apply, bool $clear = false, bool $rebalance = false, ?string $randomSeed = null): array
    {
        $this->guard();
        abort_if($randomSeed !== null && (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $randomSeed) || $clear), 422, 'Seed ngẫu nhiên không hợp lệ hoặc không được kết hợp với --clear.');
        $rebalance = $rebalance || $randomSeed !== null;

        return DB::connection('ops')->transaction(function () use ($apply, $clear, $rebalance, $randomSeed) {
            $db = DB::connection('ops');
            $result = ['mode' => $clear ? 'clear' : 'seed', 'apply' => $apply, 'eligible' => [], 'changed' => [], 'skipped' => []];
            if ($randomSeed !== null) {
                $result['mode'] = 'randomize';
                $result['random_seed'] = $randomSeed;
            }
            $rows = $db->table('airports')->orderBy('code')->lockForUpdate()->get();
            $plan = $clear ? [] : $this->plan($rows, $rebalance, $randomSeed);
            $result['targets'] = $this->targets($rows->count());
            foreach ($rows as $row) {
                if ($clear) {
                    if (($row->operational_status_source ?? null) !== 'SIMULATION' || ! in_array($row->operational_status_simulation_key, self::KEYS, true)) {
                        continue;
                    }
                    if (! hash_equals($row->operational_status_simulation_fingerprint ?? '', self::fingerprint($row))) {
                        $result['skipped'][] = $row->code;

                        continue;
                    }
                    $changes = array_fill_keys(array_merge(self::FIELDS, ['operational_status_simulation_fingerprint']), null);
                } else {
                    if (! isset($plan[$row->code])) {
                        $result['skipped'][] = $row->code;

                        continue;
                    }
                    if ($this->managed($row) && $row->operational_status === $plan[$row->code] && $row->operational_status_simulation_key === self::KEY) {
                        continue;
                    }
                    $changes = ['operational_status' => $plan[$row->code], 'operational_status_note' => self::NOTE, 'operational_status_updated_by' => null, 'operational_status_updated_at' => now()->format('Y-m-d H:i:s'), 'operational_status_source' => 'SIMULATION', 'operational_status_simulation_key' => self::KEY];
                    $changes['operational_status_simulation_fingerprint'] = self::fingerprint($changes);
                }
                $result['eligible'][] = $row->code;
                if (! $apply) {
                    continue;
                }
                $db->table('airports')->where('code', $row->code)->update($changes);
                Audit::record($clear ? 'airport.operational_status.simulation.cleared' : 'airport.operational_status.simulated', $row->code, json_encode(array_intersect_key((array) $row, array_flip(array_merge(self::FIELDS, ['operational_status_simulation_fingerprint']))), JSON_THROW_ON_ERROR), json_encode($changes + ($randomSeed !== null ? ['random_seed' => $randomSeed] : []), JSON_THROW_ON_ERROR), null, 'ops');
                $result['changed'][] = $row->code;
            }

            return $result;
        }, 3);
    }
}
