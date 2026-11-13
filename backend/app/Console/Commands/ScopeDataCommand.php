<?php

namespace App\Console\Commands;

use App\Support\ScopeData;
use Illuminate\Console\Command;

class ScopeDataCommand extends Command
{
    protected $signature = 'airline:scope-data {mode : inventory, plan, or apply} {file : Private JSON output/input path} {--receipt= : Verified rehearsal receipt required for live apply}';

    protected $description = 'Inventory/hash all three nodes, dry-run or prune exact reviewed telemetry IDs';

    public function handle(): int
    {
        $mode = $this->argument('mode');
        $path = $this->argument('file');
        if (! in_array($mode, ['inventory', 'plan', 'apply'], true)) {
            return self::INVALID;
        }
        if ($mode === 'apply') {
            $plan = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (config('database.connections.ops.database') === 'airline') {
                $receiptPath = $this->option('receipt');
                if (!is_string($receiptPath) || !is_file($receiptPath)) {
                    throw new \RuntimeException('A verified rehearsal receipt is required for the local website database.');
                }
                $receipt = json_decode(file_get_contents($receiptPath), true, 512, JSON_THROW_ON_ERROR);
                if (($receipt['plan_sha256'] ?? '') !== hash_file('sha256', $path) || empty($receipt['restore_verified']) || empty($receipt['regression_passed']) || empty($receipt['preserved_hashes'])) {
                    throw new \RuntimeException('Backup/restore/rehearsal evidence missing.');
                }
                if (count($receipt['backup_files'] ?? []) !== 3) {
                    throw new \RuntimeException('Three-node backup missing.');
                }
                foreach ($receipt['backup_files'] as $backup) {
                    if (! is_file($backup['path']) || hash_file('sha256', $backup['path']) !== $backup['sha256']) {
                        throw new \RuntimeException('Backup changed or missing.');
                    }
                }
            }
            $this->info('Deleted exact telemetry rows: '.ScopeData::apply($plan));
        } else {
            $data = $mode === 'inventory' ? ScopeData::inventory() : ScopeData::plan();
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            chmod($path, 0600);
            $this->info('Saved '.$mode.' to '.$path);
        }

        return self::SUCCESS;
    }
}
