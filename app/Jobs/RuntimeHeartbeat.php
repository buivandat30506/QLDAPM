<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RuntimeHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public string $scheduledAt) {}

    public function handle(): void
    {
        Cache::put('runtime:worker', ['scheduled_at' => $this->scheduledAt, 'completed_at' => now('UTC')->toIso8601String()], now()->addMinutes(5));
    }
}
