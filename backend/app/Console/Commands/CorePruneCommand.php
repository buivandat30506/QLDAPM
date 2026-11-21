<?php

namespace App\Console\Commands;

use App\Support\CorePruning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CorePruneCommand extends Command
{
    protected $signature = 'airline:core-prune {mode : inventory, plan or apply} {file} {--inventory=}';

    protected $description = 'Opt-in pruning of the dedicated core copy; original airline database is never modified';

    public function handle(): int
    {
        $mode = $this->argument('mode');
        $file = $this->argument('file');
        if ($mode === 'inventory') {
            $result = CorePruning::inventory();
        } elseif ($mode === 'plan') {
            $result = CorePruning::plan(json_decode(file_get_contents($this->option('inventory')), true, 512, JSON_THROW_ON_ERROR));
        } elseif ($mode === 'apply') {
            $plan = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            CorePruning::guard();
            $complete = true;
            foreach (CorePruning::DROP as $node => $tables) {
                foreach ($tables as $table) {
                    if (Schema::connection($node)->hasTable($table)) {
                        $complete = false;
                    }
                }
                if (DB::connection($node)->selectOne('SELECT @@server_uuid AS uuid')->uuid !== $plan['nodes'][$node]['uuid']) {
                    throw new \RuntimeException('Topology mismatch');
                }
            }
            if ($complete) {
                if (DB::table('bookings')->whereIn('id', $plan['booking_ids'])->exists()) {
                    throw new \RuntimeException('Drop profile is complete but archived booking rows remain');
                }
                $this->info('This exact compact profile is already applied; no database changes.');

                return self::SUCCESS;
            }
            $inventory = CorePruning::inventory();
            $result = CorePruning::apply($plan, $inventory);
            $file = storage_path('app/private/core-pruning/applied.json');
        } else {
            return self::INVALID;
        }
        file_put_contents($file, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        chmod($file, 0600);
        $this->info('Saved '.$mode.' evidence to '.$file);

        return self::SUCCESS;
    }
}
