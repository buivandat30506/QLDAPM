<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\DeliverFlightChanges)->name('flight-change-delivery')->everyMinute()->withoutOverlapping(2);

Schedule::call(fn () => app(\App\Services\Core\MaintenanceWorkflow::class)->refreshAlerts())
    ->name('maintenance-alerts')->everyFiveMinutes()->withoutOverlapping(5);
Artisan::command('maintenance:alerts', function () { $this->info('Đã kiểm tra '.app(\App\Services\Core\MaintenanceWorkflow::class)->refreshAlerts().' kế hoạch.'); });

Schedule::call(function () {
    $timestamp=now('UTC')->toIso8601String();
    \Illuminate\Support\Facades\Cache::put('runtime:scheduler', $timestamp, now()->addMinutes(5));
    \App\Jobs\RuntimeHeartbeat::dispatch($timestamp);
})->name('runtime-heartbeat')->everyMinute()->withoutOverlapping(2);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
