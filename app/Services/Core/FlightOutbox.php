<?php

namespace App\Services\Core;

use App\Integrations\Operations\FlightChangeDelivery;
use Illuminate\Support\Facades\DB;

class FlightOutbox
{
    public function drain(int $limit = 50): int
    {
        $count=0;
        foreach (DB::table('flight_outbox')->whereIn('status',['PENDING','RETRY','SENDING'])->where('available_at','<=',now('UTC'))->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $row=DB::transaction(function () use ($id) {
                $row=DB::table('flight_outbox')->where('id',$id)->lockForUpdate()->first();
                if (!in_array($row->status,['PENDING','RETRY','SENDING']) || $row->available_at>now('UTC')->format('Y-m-d H:i:s')) { return null; }
                DB::table('flight_outbox')->where('id',$id)->update(['status'=>'SENDING','attempts'=>$row->attempts+1,'available_at'=>now('UTC')->addMinutes(5),'updated_at'=>now('UTC')]);
                return $row;
            });
            if (!$row) { continue; }
            try {
                app(FlightChangeDelivery::class)->deliver($row->id,json_decode($row->payload,true,512,JSON_THROW_ON_ERROR));
                DB::table('flight_outbox')->where('id',$id)->update(['status'=>'DELIVERED','last_error'=>null,'delivered_at'=>now('UTC'),'updated_at'=>now('UTC')]); $count++;
            } catch (\Throwable $e) {
                // Exception strings can contain provider credentials. Store only the exception class.
                DB::table('flight_outbox')->where('id',$id)->update(['status'=>$row->attempts>=9?'ERROR':'RETRY','last_error'=>get_class($e),
                    'available_at'=>now('UTC')->addSeconds(min(3600,30*(2**min($row->attempts,7)))),'updated_at'=>now('UTC')]);
            }
        }
        return $count;
    }

    public function retry(string $flightId, int $id): void
    {
        app(FlightOperations::class)->find($flightId,'write');
        $row=DB::table('flight_outbox')->where('flight_id',$flightId)->where('id',$id)->firstOrFail();
        if (!in_array($row->status,['ERROR','RETRY'])) { app(FlightOperations::class)->fail('outbox','Chỉ thử lại thông báo lỗi.'); }
        DB::table('flight_outbox')->where('id',$id)->update(['status'=>'RETRY','available_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        AuditService::record('flight_outbox',$id,'RETRY',null,['flight_id'=>$flightId]);
    }
}
