<?php

namespace App\Services\Core;

use App\Models\OperationalFlight;
use App\Shared\CanonicalJson;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FlightOperations
{
    public function query(array $filters = [])
    {
        Validator::make($filters, ['search'=>'nullable|string|max:100', 'date'=>'nullable|date_format:Y-m-d',
            'flight_status'=>'nullable|in:SCHEDULED,BOARDING,DELAYED,DEPARTED,ARRIVED,CANCELLED', 'per_page'=>'nullable|integer|between:1,100'])->validate();
        return FlightAccess::query(OperationalFlight::with(['route.originAirport','route.destAirport','aircraft.model']))
            ->when($filters['search'] ?? null, fn ($q,$v) => $q->where('flight_number','like','%'.$v.'%'))
            ->when($filters['date'] ?? null, fn ($q,$v) => $q->whereDate('flight_date',$v))
            ->when($filters['flight_status'] ?? null, fn ($q,$v) => $q->where('flight_status',$v))
            ->orderByDesc('scheduled_dep_utc')->orderBy('id');
    }

    public function find(string $id, string $action = 'read'): OperationalFlight
    {
        $f = OperationalFlight::with(['route.originAirport','route.destAirport','aircraft.model'])->findOrFail($id);
        FlightAccess::authorize($action,$f); return $f;
    }

    public static function revision(OperationalFlight $f, array $data): void
    {
        if (isset($data['expected_revision']) && (int)$data['expected_revision'] !== $f->revision) {
            throw ValidationException::withMessages(['expected_revision'=>'Chuyến đã thay đổi. Tải lại và xem tác động trước khi quyết định.']);
        }
    }

    public static function record(OperationalFlight $f, string $action, string $reason): void
    {
        $f->revision++; $f->save();
        DB::table('flight_operation_history')->insert(['flight_id'=>$f->id,'revision'=>$f->revision,'action'=>$action,
            'actor_id'=>Auth::id(),'reason'=>$reason,'snapshot'=>json_encode($f->getAttributes()),'created_at'=>now('UTC')]);
        DB::table('flight_outbox')->insert(['flight_id'=>$f->id,'revision'=>$f->revision,'topic'=>'FLIGHT_CHANGED',
            'payload'=>json_encode(['schema'=>'flight-change.v1','flight_id'=>$f->id,'revision'=>$f->revision,'action'=>$action,
                'flight_status'=>$f->flight_status,'scheduled_dep_utc'=>$f->scheduled_dep_utc->toIso8601String(),
                'scheduled_arr_utc'=>$f->scheduled_arr_utc->toIso8601String(),'resource_id'=>$f->resource_id,'reason'=>$reason]),
            'status'=>'PENDING','attempts'=>0,'available_at'=>now('UTC'),'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
    }

    public function impact(OperationalFlight $f): array
    {
        // No names, documents, contact details or payment secrets in operational snapshots.
        return ['flight_revision'=>$f->revision,'aircraft_tail'=>$f->aircraft_tail,'resource_id'=>$f->resource_id,
            'orders'=>$f->orderItems()->orderBy('id')->get(['id','order_id'])->toArray(),
            'checkins'=>$f->checkinRecords()->orderBy('id')->get(['id','seat_number'])->toArray(),
            'baggage'=>$f->baggageTags()->orderBy('tag_code')->pluck('tag_code')->all(),
            'crew'=>$f->crewAssignments()->where('status','!=','REPLACED')->orderBy('id')->get(['id','revision','status'])->toArray(),
            'following'=>OperationalFlight::where('aircraft_tail',$f->aircraft_tail)->where('id','!=',$f->id)
                ->where('scheduled_dep_utc','>=',$f->scheduled_dep_utc)->whereNotIn('flight_status',['ARRIVED','CANCELLED'])->orderBy('id')->get(['id','flight_number','revision'])->toArray(),
            'maintenance'=>DB::table('maintenance_plans')->where('aircraft_tail',$f->aircraft_tail)->where('status','ACTIVE')->orderBy('id')->get(['id','revision'])->toArray(),
            'finance'=>'Đối chiếu đơn hàng bị ảnh hưởng; không tự hoàn tiền hoặc phát hành lại.',
            'ground'=>'Thông báo đổi giờ/cửa cho đơn vị mặt đất qua outbox.'];
    }

    public function checkin(string $id, array $data): OperationalFlight
    {
        Validator::make($data,['expected_revision'=>'required|integer|min:0','mode'=>'required|in:AUTO,OPEN,CLOSED','reason'=>'required|string|max:500'])->validate();
        return DB::transaction(function () use ($id,$data) {
            PlanningLock::acquire(); $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f); self::revision($f,$data);
            if (in_array($f->flight_status,['DEPARTED','ARRIVED','CANCELLED']) || $f->closed_at_utc) { $this->fail('mode','Chuyến đã khởi hành hoặc kết thúc.'); }
            // OPEN is still bounded by the published 24h / 45m eligibility window.
            $f->checkin_mode=$data['mode']; self::record($f,'CHECKIN_'.$data['mode'],$data['reason']); return $f;
        });
    }

    public function milestone(string $id, array $data): object
    {
        Validator::make($data,['expected_revision'=>'required|integer|min:0','code'=>'required|regex:/^[A-Z0-9_-]{1,40}$/',
            'label'=>'required|string|max:150','status'=>'required|in:PENDING,DONE,EXEMPT','evidence'=>'required|string|max:1000'])->validate();
        return DB::transaction(function () use ($id,$data) {
            PlanningLock::acquire(); $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f); self::revision($f,$data);
            if ($f->closed_at_utc || $f->flight_status==='CANCELLED') { $this->fail('status','Không sửa mốc của chuyến đã đóng/hủy.'); }
            DB::table('flight_milestones')->updateOrInsert(['flight_id'=>$id,'code'=>$data['code']],['label'=>$data['label'],'status'=>$data['status'],
                'mandatory'=>true,'evidence'=>$data['evidence'],'actor_id'=>Auth::id(),'completed_at'=>$data['status']==='PENDING'?null:now('UTC')]);
            self::record($f,'MILESTONE_'.$data['status'],$data['evidence']);
            return DB::table('flight_milestones')->where('flight_id',$id)->where('code',$data['code'])->first();
        });
    }

    public function close(string $id, array $data): OperationalFlight
    {
        Validator::make($data,['expected_revision'=>'required|integer|min:0','reason'=>'required|string|max:500'])->validate();
        return DB::transaction(function () use ($id,$data) {
            PlanningLock::acquire(); $f=OperationalFlight::lockForUpdate()->findOrFail($id); FlightAccess::authorize('write',$f); self::revision($f,$data);
            if ($f->closed_at_utc) { return $f; }
            if ($f->flight_status!=='ARRIVED' || !$f->actual_dep_utc || !$f->actual_arr_utc) { $this->fail('status','Chỉ đóng chuyến đã hạ cánh với đủ mốc thực tế.'); }
            if (DB::table('flight_milestones')->where('flight_id',$id)->where('mandatory',true)->where('status','PENDING')->exists()) { $this->fail('milestones','Còn mốc bắt buộc chưa hoàn tất; cần hoàn thành hoặc ghi quyết định ngoại lệ có lý do.'); }
            if (DB::table('flight_events')->where('flight_id',$id)->where('status','ALERT')->exists()) { $this->fail('events','Cần quyết định các cảnh báo còn mở trước khi đóng chuyến.'); }
            $f->closed_at_utc=now('UTC'); $f->closed_by=Auth::id(); $f->checkin_mode='CLOSED'; self::record($f,'CLOSE',$data['reason']); return $f;
        });
    }

    public function fail(string $field, string $message): never { throw ValidationException::withMessages([$field=>$message]); }
    public function hash(array $impact): string { return hash('sha256',CanonicalJson::encode($impact)); }
}
