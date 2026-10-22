<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CreateFlightWithCrewRequest;
use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Services\Core\FlightEventService;
use App\Services\Core\FlightManagementService;
use App\Services\Core\FlightOperations;
use App\Services\Core\FlightOutbox;
use App\Services\Core\FlightRecovery;
use App\Services\Core\CrewService;
use App\Services\Core\ScheduleService;
use App\Shared\Security\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FlightOperationsController extends Controller
{
    public function bulk(Request $r, string $action)
    {
        $service=app(\App\Services\Core\FlightBulkRecovery::class);
        if ($action==='preview') {
            $preview=$service->preview($r->all());
            return $r->is('api/*')?response()->json(['success'=>true,'data'=>$preview]):view('operations.bulk',compact('preview'));
        }
        $result=$service->commit($r->all());
        return $r->is('api/*')?response()->json(['success'=>true,'data'=>$result]):redirect()->route('admin.flights')->with('success','Đã áp dụng nguyên tử '.$result['applied'].' chuyến.');
    }
    public function index(Request $r, FlightOperations $ops)
    {
        $access = app(AccessControl::class);
        $crewWriteScopes = $access->scopes($r->user(), 'crew.write');
        $canAssignCrewOnCreate = Gate::allows('flight.write')
            && $access->scopes($r->user(), 'crew.read')->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']))
            && $crewWriteScopes->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
        $creationCrew = $canAssignCrewOnCreate
            ? app(CrewService::class)->query(['status' => 'AVAILABLE'])
                ->when(! $crewWriteScopes->contains('scope_type', 'global'), fn ($query) => $query
                    ->whereIn('base_airport_code', $crewWriteScopes->where('scope_type', 'airport')->pluck('scope_id')))
                ->get(['crew_members.id', 'crew_code', 'full_name', 'rank', 'base_airport_code'])
            : collect();
        $schedules = app(ScheduleService::class)->query(['status' => 'PUBLISHED'])->get();
        $crewAssignableScheduleIds = $schedules->filter(fn ($schedule) => $canAssignCrewOnCreate
            && ($crewWriteScopes->contains('scope_type', 'global')
                || $crewWriteScopes->contains(fn ($scope) => $scope->scope_type === 'airport'
                    && $scope->scope_id === $schedule->route->origin_airport_code)))
            ->pluck('id')->all();

        return view('operations.index',['flights'=>$ops->query($r->query())->paginate(20)->withQueryString(),
            'schedules' => $schedules, 'crewAssignableScheduleIds' => $crewAssignableScheduleIds,
            'creationCrew' => $creationCrew, 'canAssignCrewOnCreate' => $canAssignCrewOnCreate]);
    }

    public function show(string $id, FlightOperations $ops)
    {
        $flight=$ops->find($id); $events=app(FlightEventService::class)->query()->where('flight_id',$id)->get();
        $history=DB::table('flight_operation_history')->where('flight_id',$id)->orderByDesc('revision')->paginate(20);
        $proposals=DB::table('flight_recovery_proposals')->where('flight_id',$id)->orderByDesc('id')->get();
        return view('operations.show',['flight'=>$flight,'events'=>$events,'history'=>$history,'proposals'=>$proposals,
            'impact'=>$ops->impact($flight),'milestones'=>DB::table('flight_milestones')->where('flight_id',$id)->get(),
            'outbox'=>DB::table('flight_outbox')->where('flight_id',$id)->orderByDesc('id')->limit(50)->get(),
            'actors'=>DB::table('users')->whereIn('id',$history->pluck('actor_id')->merge($proposals->pluck('proposed_by'))->merge($proposals->pluck('decided_by'))->filter())->pluck('username','id'),
            'resources'=>AirportResource::where('airport_code',$flight->route->origin_airport_code)->whereIn('resource_type',['GATE','STAND'])->get(),
            'aircrafts'=>Aircraft::where('operational_status','OPERATIONAL')->orderBy('tail_number')->get(['tail_number'])]);
    }

    public function store(CreateFlightWithCrewRequest $r)
    {
        $data = $r->validated();
        $crew = $data['crew'] ?? [];
        unset($data['crew']);

        $f = DB::transaction(function () use ($data, $crew) {
            $flight = app(FlightManagementService::class)->createOperationalFlight($data);
            foreach ($crew as $position => $memberIds) {
                foreach ($memberIds as $index => $memberId) {
                    if (! $memberId) { continue; }
                    try {
                        app(CrewService::class)->assignCrewToFlight($flight->id, [
                            'crew_id' => $memberId, 'assigned_position' => $position,
                        ]);
                    } catch (ValidationException $error) {
                        throw ValidationException::withMessages([
                            'crew.'.$position.'.'.$index => collect($error->errors())->flatten()->all(),
                        ]);
                    }
                }
            }

            return $flight;
        });

        $hasCrew = collect($crew)->flatten()->filter()->isNotEmpty();
        return redirect()->route('admin.flights.show',$f->id)->with('success', $hasCrew
            ? 'Đã tạo chuyến và lưu phân công phi hành đoàn. Phân công cần được duyệt và xác nhận.'
            : 'Đã tạo chuyến từ lịch công bố.');
    }

    public function act(Request $r, string $id, string $action)
    {
        app(FlightOperations::class)->find($id,'write');
        $data=$r->except(['_token']);
        $result=match($action) {
            'status'=>app(FlightManagementService::class)->updateFlightStatus($id,$r->validate(['flight_status'=>'required|in:SCHEDULED,BOARDING,DELAYED,DEPARTED,ARRIVED,CANCELLED',
                'expected_revision'=>'required|integer|min:0','actual_dep_utc'=>'nullable|date','actual_arr_utc'=>'nullable|date','delay_reason'=>'required|string|max:500'])),
            'checkin'=>app(FlightOperations::class)->checkin($id,$data), 'close'=>app(FlightOperations::class)->close($id,$data),
            'milestone'=>app(FlightOperations::class)->milestone($id,$data),
            'events'=>app(FlightEventService::class)->receive(array_replace($data,['flight_id'=>$id])),
            'proposals'=>app(FlightRecovery::class)->propose($id,$data),
        };
        if ($r->is('api/*')) { return response()->json(['success'=>($result->status??'')!=='FAILED','data'=>$result],($result->status??'')==='FAILED'?422:200); }
        if (($result->status??'')==='FAILED') { return back()->withErrors(['event'=>$result->message])->withInput(); }
        return redirect()->route('admin.flights.show',$id)->with('success','Đã lưu thao tác và lịch sử.');
    }

    public function decide(Request $r, string $id, int $proposal)
    {
        $f=app(FlightRecovery::class)->decide($id,$proposal,$r->all());
        return $r->is('api/*')?response()->json(['success'=>true,'data'=>$f]):redirect()->route('admin.flights.show',$id)->with('success','Đã ghi quyết định phương án.');
    }

    public function retry(Request $r, int $event)
    {
        $e=app(FlightEventService::class)->retry($event);
        return $r->is('api/*')?response()->json(['success'=>$e->status!=='FAILED','data'=>$e],$e->status==='FAILED'?422:200):back()->with('success','Đã thử lại; xem trạng thái sự kiện.');
    }

    public function retryOutbox(Request $r, string $id, int $message)
    {
        app(FlightOutbox::class)->retry($id,$message);
        return $r->is('api/*')?response()->json(['success'=>true]):back()->with('success','Đã đưa thông báo lỗi vào hàng đợi thử lại.');
    }

    public function apiDetail(string $id, FlightOperations $ops)
    {
        $f=$ops->find($id);
        return response()->json(['success'=>true,'data'=>['flight'=>$f,'impact'=>$ops->impact($f),
            'history'=>DB::table('flight_operation_history')->where('flight_id',$id)->orderByDesc('revision')->paginate(25),
            'proposals'=>DB::table('flight_recovery_proposals')->where('flight_id',$id)->orderByDesc('id')->get(),
            'milestones'=>DB::table('flight_milestones')->where('flight_id',$id)->get(),
            'outbox'=>DB::table('flight_outbox')->where('flight_id',$id)->orderByDesc('id')->limit(50)->get()]]);
    }
}
