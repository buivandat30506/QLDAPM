<?php

namespace Tests\Unit;

use App\Models\FlightSchedule;
use App\Services\Core\ScheduleOccurrence;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleOccurrenceTest extends TestCase
{
    public function test_daily_local_recurrence_preserves_wall_time_across_dst(): void
    {
        $schedule = new FlightSchedule(['departure_time_local' => '10:00:00', 'arrival_time_local' => '12:00:00', 'source_timezone' => 'America/New_York', 'arrival_day_offset' => 0]);
        $service = new ScheduleOccurrence;
        [$before] = $service->times($schedule, Carbon::parse('2026-10-31', 'UTC'));
        [$after] = $service->times($schedule, Carbon::parse('2026-11-01', 'UTC'));
        $this->assertSame('2026-10-31 14:00:00', $before->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 15:00:00', $after->format('Y-m-d H:i:s'));
    }

    public function test_nonexistent_spring_hour_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new ScheduleOccurrence)->times(new FlightSchedule(['departure_time_local' => '02:30:00', 'arrival_time_local' => '04:30:00', 'source_timezone' => 'America/New_York', 'arrival_day_offset' => 0]), Carbon::parse('2026-03-08', 'UTC'));
    }

    public function test_ambiguous_autumn_hour_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new ScheduleOccurrence)->times(new FlightSchedule(['departure_time_local' => '01:30:00', 'arrival_time_local' => '04:30:00', 'source_timezone' => 'America/New_York', 'arrival_day_offset' => 0]), Carbon::parse('2026-11-01', 'UTC'));
    }

    public function test_explicit_local_overnight_and_legacy_utc_overnight(): void
    {
        $service = new ScheduleOccurrence;
        [$dep, $arr] = $service->times(new FlightSchedule(['departure_time_local' => '23:00:00', 'arrival_time_local' => '02:00:00', 'source_timezone' => 'Asia/Ho_Chi_Minh', 'arrival_day_offset' => 1]), Carbon::parse('2026-11-09', 'UTC'));
        $this->assertSame('2026-11-09 16:00:00', $dep->format('Y-m-d H:i:s')); $this->assertSame('2026-11-09 19:00:00', $arr->format('Y-m-d H:i:s'));
        [$dep, $arr] = $service->times(new FlightSchedule(['departure_time_utc' => '23:00:00', 'arrival_time_utc' => '02:00:00']), Carbon::parse('2026-11-09', 'UTC'));
        $this->assertSame(3.0, $dep->diffInHours($arr)); $this->assertSame('2026-11-10', $arr->toDateString());
    }
}
