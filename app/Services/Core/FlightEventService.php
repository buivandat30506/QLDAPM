<?php

namespace App\Services\Core;

use App\Models\OperationalFlight;
use App\Shared\CanonicalJson;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FlightEventService
{
    public function query()
    {
        $ids=FlightAccess::query(OperationalFlight::query())->select('id');
        return DB::table('flight_events')->when(Auth::check() && !Gate::allows('flight.read'), fn($q)=>$q->whereIn('flight_id',$ids))->orderByDesc('id');
    }

    public function receive(array $input): object
    {
        $data=Validator::make($input,['source'=>'required|in:AODB,FIDS,DEMO','external_id'=>'required|string|max:100','flight_id'=>'required|uuid',
            'flight_status'=>'required|in:SCHEDULED,BOARDING,DEPARTED,ARRIVED,DELAYED,CANCELLED', 'schema'=>'nullable|in:flight-event.v1',
            'source_time_utc'=>'required_with:schema|date','source_timezone'=>'required_with:schema|timezone:all','sequence'=>'required_with:schema|integer|min:1|max:2147483647',
            'estimated_dep_utc'=>'nullable|date','estimated_arr_utc'=>'nullable|date','actual_dep_utc'=>'nullable|date','actual_arr_utc'=>'nullable|date','reason'=>'nullable|string|max:500'])->validate();
        return DB::transaction(function () use ($data) {
            PlanningLock::acquire();
            $flight=OperationalFlight::lockForUpdate()->find($data['flight_id']);
            if ($flight) { FlightAccess::authorize('write',$flight); } elseif(Auth::check()) { Gate::authorize('flight.write'); }
            $existing=DB::table('flight_events')->where('source',$data['source'])->where('external_id',$data['external_id'])->first();
            if ($existing) {
                if (CanonicalJson::encode(json_decode($existing->payload,true))!==CanonicalJson::encode($data)) {
                    throw ValidationException::withMessages(['external_id'=>'Mã sự kiện đã dùng với nội dung khác.']);
                }
                return $existing;
            }
            $key=DB::table('flight_events')->insertGetId(['source'=>$data['source'],'external_id'=>$data['external_id'],'flight_id'=>$data['flight_id'],
                'payload'=>json_encode($data),'status'=>'RECEIVED','source_time_utc'=>isset($data['source_time_utc'])?Carbon::parse($data['source_time_utc'],'UTC')->utc():now('UTC'),
                'source_timezone'=>$data['source_timezone']??'UTC','sequence'=>$data['sequence']??null,
                'profile'=>isset($data['schema'])?'local-simulation-v1':'legacy-local','received_by'=>Auth::id(),'attempts'=>0,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
            return $this->process($key,$flight);
        });
    }

    public function retry(int $id): object
    {
        return DB::transaction(function () use ($id) {
            PlanningLock::acquire(); $e=DB::table('flight_events')->where('id',$id)->lockForUpdate()->firstOrFail();
            $f=OperationalFlight::lockForUpdate()->find($e->flight_id);
            if ($f) { FlightAccess::authorize('write',$f); } elseif(Auth::check()) { Gate::authorize('flight.write'); }
            if ($e->status!=='FAILED') { throw ValidationException::withMessages(['event'=>'Chỉ thử lại sự kiện lỗi.']); }
            return $this->process($id,$f);
        });
    }

    private function process(int $id, ?OperationalFlight $f): object
    {
        $e=DB::table('flight_events')->find($id); $data=json_decode($e->payload,true,512,JSON_THROW_ON_ERROR);
        DB::table('flight_events')->where('id',$id)->increment('attempts');
        $status='PROCESSED'; $message='Đã cập nhật sự kiện hợp lệ.';
        try {
            DB::transaction(function () use ($id,$e,$data,$f,&$status,&$message) {
                if (!$f) { throw ValidationException::withMessages(['flight_id'=>'Không xác định được chuyến bay.']); }
                $at=Carbon::parse($e->source_time_utc,'UTC');
                if ($at->greaterThan(now('UTC')->addMinutes(5))) { throw ValidationException::withMessages(['source_time_utc'=>'Mốc nguồn ở tương lai; kiểm tra đồng hồ tích hợp.']); }
                $last=DB::table('flight_events')->where('flight_id',$f->id)->where('source',$e->source)->where('id','!=',$id)
                    ->whereIn('status',['PROCESSED','ALERT','RESOLVED','IGNORED'])->whereNotNull('sequence')->max('sequence');
                if ($f->closed_at_utc || ($f->latest_event_at_utc && $at->lessThan($f->latest_event_at_utc)) || ($e->sequence!==null && $last!==null && $e->sequence<=$last)) {
                    $status='IGNORED'; $message='Giữ sự kiện cũ/đảo thứ tự hoặc chuyến đã đóng; không ghi đè dữ liệu mới.'; return;
                }
                foreach (['actual_dep_utc','actual_arr_utc'] as $field) {
                    if (!empty($data[$field]) && Carbon::parse($data[$field],'UTC')->greaterThan(now('UTC')->addMinutes(5))) {
                        throw ValidationException::withMessages([$field=>'Mốc thực tế không được ở tương lai.']);
                    }
                }
                $dep=!empty($data['estimated_dep_utc'])?Carbon::parse($data['estimated_dep_utc'],'UTC')->utc():($f->estimated_dep_utc??$f->scheduled_dep_utc);
                $arr=!empty($data['estimated_arr_utc'])?Carbon::parse($data['estimated_arr_utc'],'UTC')->utc():($f->estimated_arr_utc??$f->scheduled_arr_utc);
                if ($arr->lessThanOrEqualTo($dep)) { throw ValidationException::withMessages(['estimated_arr_utc'=>'Giờ đến dự kiến phải sau giờ đi dự kiến.']); }
                $abnormal=in_array($data['flight_status'],['DELAYED','CANCELLED']) || $f->scheduled_dep_utc->diffInMinutes($dep,false)>=config('operations.delay_alert_minutes',15);
                if (!in_array($data['flight_status'],['DELAYED','CANCELLED'])) {
                    app(FlightManagementService::class)->updateFlightStatus($f->id,$data); $f->refresh();
                }
                $f->estimated_dep_utc=$dep; $f->estimated_arr_utc=$arr; $f->latest_event_at_utc=$at;
                FlightOperations::record($f,'EVENT_RECEIVED','Nguồn '.$e->source.' / '.$e->external_id);
                if ($abnormal) { $status='ALERT'; $message='Bất thường chuyến '.$f->flight_number.'. Cần Điều hành chọn phương án; chưa tự đổi kế hoạch.'; }
            });
        } catch (ValidationException $error) { $status='FAILED'; $message=json_encode($error->errors(),JSON_UNESCAPED_UNICODE); }
        DB::table('flight_events')->where('id',$id)->update(['status'=>$status,'message'=>$message,'updated_at'=>now('UTC')]);
        $after=DB::table('flight_events')->find($id); AuditService::record('flight_events',$id,$status,null,$after); return $after;
    }

    public function resolve(string $id, array $data): OperationalFlight
    {
        // Compatibility endpoint: the logged-in operator still makes an explicit recorded decision.
        $f=app(FlightOperations::class)->find($id,'write');
        $event=DB::table('flight_events')->where('flight_id',$id)->where('status','ALERT')->orderByDesc('id')->first();
        if (!$event) { throw ValidationException::withMessages(['event'=>'Cần cảnh báo đang mở trước khi lập phương án.']); }
        $p=app(FlightRecovery::class)->propose($id,array_replace($data,['event_id'=>$event->id,'expected_revision'=>$data['expected_revision']??$f->revision]));
        return app(FlightRecovery::class)->decide($id,$p->id,['decision'=>'APPLY','reason'=>$data['reason']]);
    }
}
