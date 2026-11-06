<?php

namespace App\Services\Core;

use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CrewCalendar
{
    public function add(string $id, array $input): object
    {
        return DB::transaction(function () use ($id, $input) {
            PlanningLock::acquire();
            $crew = CrewMember::lockForUpdate()->findOrFail($id);
            CrewAccess::member('write', $crew);
            $data = Validator::make($input, ['type' => 'required|in:LEAVE,UNAVAILABLE,STANDBY', 'starts_at_utc' => 'required|date',
                'ends_at_utc' => 'required|date|after:starts_at_utc', 'reason' => 'required|string|max:500', 'expected_revision' => 'required|integer|min:1'])->validate();
            abort_if((int) $data['expected_revision'] !== $crew->revision, 409, 'Lịch nhân sự đã thay đổi; tải lại.');
            foreach (['starts_at_utc', 'ends_at_utc'] as $field) {
                $data[$field] = Carbon::parse($data[$field], 'UTC')->format('Y-m-d H:i:s');
            }
            $active = FlightCrewAssignment::where('crew_id', $id)->where('status', '!=', 'REPLACED')->where('duty_start_utc', '<', $data['ends_at_utc'])->where('duty_end_utc', '>', $data['starts_at_utc'])
                ->whereHas('flight', fn ($q) => $q->whereNotIn('flight_status', ['ARRIVED', 'CANCELLED']))->get();
            if ($data['type'] !== 'STANDBY' && $active->contains(fn ($a) => $a->flight->flight_status === 'DEPARTED')) {
                throw ValidationException::withMessages(['starts_at_utc' => ['Khoảng không khả dụng ảnh hưởng chuyến đang bay; cần điều hành xử lý riêng.']]);
            }
            unset($data['expected_revision']);
            $windowId = DB::table('crew_availability_windows')->insertGetId($data + ['crew_id' => $id, 'created_by' => Auth::id(), 'created_at' => now('UTC')]);
            $crew->increment('revision');
            if ($data['type'] !== 'STANDBY') {
                foreach ($active as $a) {
                    app(CrewService::class)->recordEvent($a, 'AVAILABILITY_INVALIDATED', $data['reason']);
                    $a->update(['status' => 'ASSIGNED', 'approved_at' => null, 'confirmed_at' => null, 'approved_by' => null, 'confirmed_by' => null, 'confirmation_mode' => null, 'revision' => $a->revision + 1]);
                    DB::table('crew_notifications')->insert(['assignment_id' => $a->id, 'message' => 'Phân công cần thay thế/kiểm tra lại do thay đổi khả dụng.', 'created_at' => now('UTC')]);
                }
            }
            $window = DB::table('crew_availability_windows')->find($windowId);
            AuditService::record('crew_availability_windows', $windowId, 'CREATED', null, $window);

            return $window;
        });
    }

    public function cancel(string $crewId, int $id, array $input): void
    {
        DB::transaction(function () use ($crewId, $id, $input) {
            PlanningLock::acquire();
            $crew = CrewMember::lockForUpdate()->findOrFail($crewId);
            CrewAccess::member('write', $crew);
            $data = Validator::make($input, ['reason' => 'required|string|max:500'])->validate();
            $window = DB::table('crew_availability_windows')->where('crew_id', $crewId)->where('id', $id)->first();
            abort_unless($window, 404);
            if ($window->cancelled_at) {
                return;
            }
            DB::table('crew_availability_windows')->where('id', $id)->update(['cancelled_at' => now('UTC'), 'cancelled_by' => Auth::id(), 'cancel_reason' => $data['reason']]);
            $crew->increment('revision');
            AuditService::record('crew_availability_windows',$id,'CANCELLED',$window,null,$data['reason']);
        });
    }
}
