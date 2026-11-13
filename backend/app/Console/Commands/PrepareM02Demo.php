<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Operations\M02Fixtures;
use Illuminate\Console\Command;

class PrepareM02Demo extends Command
{
    protected $signature = 'airline:m02-demo {--key=m02-local-v1} {--start=} {--creator=} {--operator=} {--reviewer=} {--fleet=} {--crew=} {--technician=} {--inspect : Read the manifest only} {--archive : Stop generation; retain flights and audit}';

    protected $description = 'Prepare reproducible M02 workflows in an explicitly enabled local/test database';

    public function handle(M02Fixtures $fixtures): int
    {
        try {
            $fixtures->guard();
            $key = $this->option('key');
            if ($this->option('inspect')) {
                $result = $fixtures->report($key);
            } else {
                $actors = [];
                foreach (['creator' => 'data_admin', 'operator' => 'operations.han', 'reviewer' => 'ops_manager.han', 'fleet' => 'fleet_manager', 'crew' => 'crew_scheduler', 'technician' => 'maintenance'] as $name => $alias) {
                    $actors[$name] = $this->option($name) ? User::findOrFail((int) $this->option($name)) : User::where('email', 'demo.'.$alias.'@airline.test')->firstOrFail();
                }$result = $this->option('archive') ? $fixtures->archive($key, $actors['creator']) : $fixtures->run($key, $this->option('start'), $actors);
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
