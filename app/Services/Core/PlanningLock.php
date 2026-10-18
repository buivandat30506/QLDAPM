<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;

class PlanningLock
{
    // Acquire before any consistent read in the transaction (MySQL REPEATABLE READ).
    // Serializes local planning mutations across routes, resources, maintenance and flights.
    public static function acquire(): void
    {
        if (DB::transactionLevel() < 1) { throw new \LogicException('Planning lock requires a transaction.'); }
        DB::table('domain_locks')->where('name', 'flight-planning')->lockForUpdate()->firstOrFail();
    }
}
