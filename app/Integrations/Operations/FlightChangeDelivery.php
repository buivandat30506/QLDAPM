<?php

namespace App\Integrations\Operations;

interface FlightChangeDelivery
{
    /** Idempotent by outboxId. Implementations must not include passenger PII in logs. */
    public function deliver(int $outboxId, array $payload): void;
}
