<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$ops=DB::connection('ops');$db=DB::connection('mysql');$fin=DB::connection('finance');
$flights=$ops->table('flights')->where('status','ARRIVED')->pluck('id');
echo 'Arrived flights '.$flights->count()."\n";
$q=$db->table('bookings')->whereIn('flight_id',$flights)->where('idempotency_key','like','demo-manifest-%');
echo 'Historical demo bookings '.$q->count()."\n";
$b=$q->orderBy('id')->first();
if($b){
 echo json_encode(['key'=>$b->idempotency_key,'status'=>$b->status,'reservation_id'=>$b->reservation_id,'passenger_id'=>$b->passenger_id])."\n";
 foreach(['checkout_sessions','tickets','boarding_passes','baggage'] as $t) echo $t.' '.json_encode($db->table($t)->where('booking_id',$b->id)->first())."\n";
 foreach(['payments','checkout_intents','ledger_entries'] as $t) echo $t.' '.json_encode($fin->table($t)->where('booking_id',$b->id)->first())."\n";
}
