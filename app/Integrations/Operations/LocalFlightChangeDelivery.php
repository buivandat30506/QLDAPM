<?php

namespace App\Integrations\Operations;

use App\Shared\CanonicalJson;
use Illuminate\Support\Facades\DB;

class LocalFlightChangeDelivery implements FlightChangeDelivery
{
    public function deliver(int $outboxId, array $payload): void
    {
        if (config('flight-operations.delivery_profile') !== 'local-simulation') {
            throw new \RuntimeException('A live flight delivery adapter has not been configured.');
        }
        if (($payload['schema'] ?? '') !== 'flight-change.v1' || empty($payload['flight_id']) || !isset($payload['revision'])) {
            throw new \InvalidArgumentException('Invalid flight-change.v1 payload.');
        }
        $hash=hash('sha256',CanonicalJson::encode($payload));
        DB::table('flight_delivery_receipts')->insertOrIgnore(['outbox_id'=>$outboxId,'adapter'=>'local-simulation-v1','payload_hash'=>$hash,'delivered_at'=>now('UTC')]);
        if (DB::table('flight_delivery_receipts')->where('outbox_id',$outboxId)->value('payload_hash') !== $hash) {
            throw new \RuntimeException('Conflicting outbox delivery payload.');
        }
    }
}
