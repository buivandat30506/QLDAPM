<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverFlightChanges implements ShouldQueue
{
    use Queueable;
    public function handle(\App\Services\Core\FlightOutbox $outbox): void { $outbox->drain(); }
}
