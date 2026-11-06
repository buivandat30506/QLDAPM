<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\OperationalFlight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FlightRecovery
{
    public function propose(string $id, array $data): object
    {
        Validator::make($data,['event_id'=>'required|integer','expected_revision'=>'required|integer|min:0',
            'action'=>'required|in:DELAY,CANCEL,REASSIGN,GATE,RESOURCE,ACKNOWLEDGE','reason'=>'required|string|max:500',
            'scheduled_dep_utc'=>'nullable|date','scheduled_arr_utc'=>'nullable|date',
            'aircraft_tail'=>'required_if:action,REASSIGN|nullable|string|max:20|exists:aircrafts,tail_number',
            'resource_id'=>'required_if:action,GATE,RESOURCE|nullable|integer|exists:airport_resources,id'])->validate();
        return DB::transaction(function () use ($id,$data) {
            PlanningLock::acquire(); $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f); FlightOperations::revision($f,$data);
            $event=DB::table('flight_events')->where('id',$data['event_id'])->where('flight_id',$id)->where('status','ALERT')->first();
            if (!$event || $f->closed_at_utc) { app(FlightOperations::class)->fail('event_id','Cần cảnh báo đang mở của đúng chuyến.'); }
            $impact=app(FlightOperations::class)->impact($f);
            $key=DB::table('flight_recovery_proposals')->insertGetId(['flight_id'=>$id,'event_id'=>$event->id,'flight_revision'=>$f->revision,
                'action'=>$data['action'],'status'=>'PROPOSED','plan'=>json_encode($data),'impact'=>json_encode($impact),
                'impact_hash'=>app(FlightOperations::class)->hash($impact),'reason'=>$data['reason'],'proposed_by'=>Auth::id(),'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
            AuditService::record('flight_recovery_proposals',$key,'PROPOSE',null,['flight_id'=>$id,'impact'=>$impact],$data['reason']);
            return DB::table('flight_recovery_proposals')->find($key);
        });
    }

    public function decide(string $id, int $proposalId, array $data): OperationalFlight
    {
        Validator::make($data,['decision'=>'required|in:APPLY,REJECT','reason'=>'required|string|max:500'])->validate();
        return DB::transaction(function () use ($id,$proposalId,$data) {
            PlanningLock::acquire(); $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f);
            if (Auth::check() && !Auth::id()) { abort(403); }
            $p=DB::table('flight_recovery_proposals')->where('flight_id',$id)->where('id',$proposalId)->lockForUpdate()->firstOrFail();
            $ops=app(FlightOperations::class);
            if ($p->status!=='PROPOSED') { $ops->fail('proposal','Phương án đã có quyết định.'); }
            if ($data['decision']==='APPLY') {
                if ($f->revision!==$p->flight_revision || $ops->hash($ops->impact($f))!==$p->impact_hash) { $ops->fail('proposal','Tác động/phiên bản đã đổi. Lập phương án mới sau khi xem lại dữ liệu.'); }
                $event=DB::table('flight_events')->where('id',$p->event_id)->where('flight_id',$id)->where('status','ALERT')->first();
                if (!$event || $f->closed_at_utc) { $ops->fail('event','Cảnh báo không còn mở.'); }
                $plan=json_decode($p->plan,true,512,JSON_THROW_ON_ERROR); $this->apply($f,$plan,$data['reason']);
                DB::table('flight_events')->where('id',$p->event_id)->update(['status'=>'RESOLVED','updated_at'=>now('UTC')]);
            }
            DB::table('flight_recovery_proposals')->where('id',$proposalId)->update(['status'=>$data['decision']==='APPLY'?'APPLIED':'REJECTED',
                'decided_by'=>Auth::id(),'decided_at'=>now('UTC'),'decision_reason'=>$data['reason'],'updated_at'=>now('UTC')]);
            AuditService::record('flight_recovery_proposals',$proposalId,$data['decision'],null,['flight_id'=>$id],$data['reason']); return $f->fresh();
        });
    }

    private function apply(OperationalFlight $f, array $plan, string $reason): void
    {
        $ops=app(FlightOperations::class); $action=$plan['action'];
        if ($action==='ACKNOWLEDGE') { FlightOperations::record($f,'ACKNOWLEDGE',$reason); return; }
        if (!in_array($f->flight_status,['SCHEDULED','BOARDING','DELAYED'])) { $ops->fail('status','Chỉ thay kế hoạch trước khởi hành.'); }
        if ($action==='CANCEL') {
            app(FlightManagementService::class)->updateFlightStatus($f->id,['flight_status'=>'CANCELLED','delay_reason'=>$reason]);
            foreach ($f->crewAssignments()->where('status','!=','REPLACED')->get() as $a) {
                app(CrewService::class)->recordEvent($a,'FLIGHT_CANCELLED',$reason);
                $a->update(['status'=>'REPLACED','revision'=>$a->revision+1]);
            }
            $f->refresh(); return;
        }
        $dep=Carbon::parse($plan['scheduled_dep_utc'] ?? $f->scheduled_dep_utc,'UTC')->utc();
        $arr=Carbon::parse($plan['scheduled_arr_utc'] ?? $f->scheduled_arr_utc,'UTC')->utc();
        if ($arr->lessThanOrEqualTo($dep)) { $ops->fail('scheduled_arr_utc','Giờ đến phải sau giờ đi.'); }
        $aircraft=Aircraft::lockForUpdate()->findOrFail($plan['aircraft_tail'] ?? $f->aircraft_tail);
        $resource=AirportResource::lockForUpdate()->findOrFail($plan['resource_id'] ?? $f->resource_id);
        app(ScheduleService::class)->checkWindow($f->route,$aircraft,$resource,$dep,$arr,$f->id,$f->schedule_id);
        $changedAircraft=$aircraft->tail_number!==$f->aircraft_tail;
        if ($changedAircraft) {
            if ($f->checkinRecords()->exists() || $f->orderItems()->exists() || $f->baggageTags()->exists()) {
                $ops->fail('aircraft_tail','Có vé/dịch vụ/check-in/hành lý. Cần quy trình chuyển chỗ và thu hồi QR trước khi đổi tàu; không tự chuyển dữ liệu ghế.');
            }
            $f->aircraft_configuration_id=$aircraft->current_configuration_id;
            $f->seat_capacity_snapshot=$aircraft->configuration?->sellable_capacity ?? $aircraft->model->seat_capacity;
        }
        $invalidate=$changedAircraft || !$dep->equalTo($f->scheduled_dep_utc) || !$arr->equalTo($f->scheduled_arr_utc);
        $f->fill(['aircraft_tail'=>$aircraft->tail_number,'resource_id'=>$resource->id,'gate_number'=>$resource->resource_code,
            'scheduled_dep_utc'=>$dep,'scheduled_arr_utc'=>$arr,'delay_reason'=>$reason]);
        if ($action==='DELAY') { $f->flight_status='DELAYED'; }
        $f->unsetRelation('aircraft');
        if ($invalidate) {
            foreach ($f->crewAssignments()->where('status','!=','REPLACED')->get() as $a) {
                [$start,$end]=app(CrewRulesService::class)->assignment($f,$a->crewMember,$a->assigned_position);
                app(CrewService::class)->recordEvent($a,'FLIGHT_PLAN_INVALIDATED',$reason);
                $a->update(['duty_start_utc'=>$start,'duty_end_utc'=>$end,'status'=>'ASSIGNED','approved_at'=>null,'confirmed_at'=>null,
                    'approved_by'=>null,'confirmed_by'=>null,'confirmation_mode'=>null,'revision'=>$a->revision+1]);
            }
        }
        FlightOperations::record($f,'RECOVERY_'.$action,$reason);
    }
}
