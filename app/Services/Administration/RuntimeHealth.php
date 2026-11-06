<?php

namespace App\Services\Administration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RuntimeHealth
{
    public function inspect(): array
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'mysql') {
                return ['status' => 'unavailable', 'database' => 'mysql_required'];
            }
            $version = $connection->selectOne('SELECT VERSION() AS version')->version;
            if (stripos($version, 'MariaDB') !== false) {
                return ['status' => 'unavailable', 'database' => 'mysql_required'];
            }
            foreach (['users', 'jobs', 'failed_jobs', 'master_data_records', 'domain_locks'] as $table) {
                $connection->table($table)->limit(1)->get();
            }
            $required = config('queue.default') === 'database';
            $scheduler = Cache::get('runtime:scheduler');
            $worker = Cache::get('runtime:worker');
            $fresh = fn (?string $time) => $time !== null && CarbonImmutable::parse($time)->betweenIncluded(now('UTC')->subSeconds(150), now('UTC')->addSeconds(5));
            $schedulerHealthy = $fresh($scheduler);
            $workerHealthy = $fresh($worker['completed_at'] ?? null);

            return ['status' => (! $required || ($schedulerHealthy && $workerHealthy)) ? 'ready' : 'unavailable',
                'database' => 'ready', 'queue_connection' => config('queue.default'), 'background_required' => $required,
                'scheduler' => $schedulerHealthy ? 'fresh' : 'not_observed_or_stale', 'worker' => $workerHealthy ? 'fresh' : 'not_observed_or_stale',
                'failed_jobs' => $connection->table('failed_jobs')->count(), 'pending_jobs' => $connection->table('jobs')->count()];
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'database' => 'unavailable'];
        }
    }
}
