<?php

namespace App\Services\Core;

use App\Models\OperationalFlight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FlightBulkRecovery
{
    public function preview(array $data): array
    {
        return DB::transaction(function () use ($data) { PlanningLock::acquire(); return $this->build($data); });
    }

    public function commit(array $data): array
    {
        Validator::make($data,['preview_hash'=>'required|string|size:64'])->validate();
        return DB::transaction(function () use ($data) {
            PlanningLock::acquire(); $preview=$this->build($data);
            if (!hash_equals($preview['hash'],$data['preview_hash'])) { app(FlightOperations::class)->fail('preview_hash','Dữ liệu đã đổi. Xem trước lại toàn bộ lô.'); }
            foreach ($preview['items'] as $item) {
                $p=app(FlightRecovery::class)->propose($item['flight_id'],$item['plan']);
                app(FlightRecovery::class)->decide($item['flight_id'],$p->id,['decision'=>'APPLY','reason'=>$preview['input']['reason']]);
            }
            return ['applied'=>count($preview['items'])];
        });
    }

    private function build(array $data): array
    {
        $data=Validator::make($data,['flight_ids'=>'required|array|min:1|max:20','flight_ids.*'=>'required|uuid|distinct',
            'action'=>'required|in:DELAY,CANCEL','delay_minutes'=>'required_if:action,DELAY|nullable|integer|between:1,1440','reason'=>'required|string|max:500'])->validate();
        sort($data['flight_ids']); $items=[];
        foreach ($data['flight_ids'] as $id) {
            $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f);
            $event=DB::table('flight_events')->where('flight_id',$id)->where('status','ALERT')->orderByDesc('id')->first();
            if (!$event || !in_array($f->flight_status,['SCHEDULED','BOARDING','DELAYED']) || $f->closed_at_utc) { app(FlightOperations::class)->fail('flight_ids','Mỗi chuyến trong lô cần cảnh báo mở và chưa khởi hành.'); }
            $plan=['event_id'=>$event->id,'expected_revision'=>$f->revision,'action'=>$data['action'],'reason'=>$data['reason']];
            if ($data['action']==='DELAY') {
                $plan['scheduled_dep_utc']=$f->scheduled_dep_utc->copy()->addMinutes((int)$data['delay_minutes'])->toIso8601String();
                $plan['scheduled_arr_utc']=$f->scheduled_arr_utc->copy()->addMinutes((int)$data['delay_minutes'])->toIso8601String();
            }
            $items[]=['flight_id'=>$id,'flight_number'=>$f->flight_number,'plan'=>$plan,'impact'=>app(FlightOperations::class)->impact($f)];
        }
        return ['input'=>$data,'items'=>$items,'hash'=>app(FlightOperations::class)->hash(['input'=>$data,'items'=>$items])];
    }
}
