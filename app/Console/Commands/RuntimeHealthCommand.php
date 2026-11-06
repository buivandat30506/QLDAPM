<?php

namespace App\Console\Commands;

use App\Services\Administration\RuntimeHealth;
use Illuminate\Console\Command;

class RuntimeHealthCommand extends Command
{
    protected $signature = 'app:health';

    protected $description = 'Check MySQL/schema and database queue/scheduler freshness without exposing credentials';

    public function handle(RuntimeHealth $health): int
    {
        $result = $health->inspect();
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'ready' ? self::SUCCESS : self::FAILURE;
    }
}
