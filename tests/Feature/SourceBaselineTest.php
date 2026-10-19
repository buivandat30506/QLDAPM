<?php

namespace Tests\Feature;

use App\Models\OperationalFlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceBaselineTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidArrivalCases(): array
    {
        return ['equal' => ['2030-01-01 10:00:00'], 'earlier' => ['2030-01-01 09:59:59']];
    }

    #[DataProvider('invalidArrivalCases')]
    public function test_ut_w1_w2_flight_02_rejects_arrival_not_after_departure(string $arrival): void
    {
        $this->seed();
        $this->actingAs(User::where('username', 'ops_demo')->firstOrFail());
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $count = OperationalFlight::count();
        $this->postJson('/api/v1/core/flights', ['schedule_id' => $flight->schedule_id, 'flight_date' => '2030-01-01', 'scheduled_dep_utc' => '2030-01-01 10:00:00', 'scheduled_arr_utc' => $arrival])->assertUnprocessable()->assertJsonValidationErrors('scheduled_arr_utc');
        $this->assertSame($count, OperationalFlight::count());
    }
}
