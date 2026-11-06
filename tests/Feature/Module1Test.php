<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('username', 'toan_lead')->firstOrFail());
    }

    public function test_airport_crud_and_validation(): void
    {
        $payload = ['iata_code' => 'VDO', 'name' => 'Vân Đồn', 'city' => 'Quảng Ninh'];
        $this->postJson('/api/v1/core/airports', $payload)->assertCreated()->assertJsonPath('data.iata_code', 'VDO');
        $this->postJson('/api/v1/core/airports', $payload)->assertUnprocessable();
        $this->putJson('/api/v1/core/airports/VDO', ['name' => 'Sân bay Vân Đồn', 'country' => null])->assertOk();
        $this->getJson('/api/v1/core/airports/VDO')->assertOk()->assertJsonPath('data.name', 'Sân bay Vân Đồn');
        $this->deleteJson('/api/v1/core/airports/VDO')->assertOk();
        $this->getJson('/api/v1/core/airports/VDO')->assertNotFound()->assertJsonPath('success', false);
        $this->deleteJson('/api/v1/core/airports/HAN')->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_route_crud_and_duplicate_constraints(): void
    {
        $payload = ['origin_airport_code' => 'PQC', 'dest_airport_code' => 'CXR', 'distance_km' => 500, 'standard_duration_minutes' => 65];
        $id = $this->postJson('/api/v1/core/routes', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/core/routes', $payload)->assertUnprocessable();
        $this->putJson("/api/v1/core/routes/{$id}", ['distance_km' => 550])->assertOk()->assertJsonPath('data.distance_km', '550.00');
        $this->putJson("/api/v1/core/routes/{$id}", ['dest_airport_code' => 'PQC'])->assertUnprocessable();
        $this->putJson("/api/v1/core/routes/{$id}", ['route_code' => 'HAN-SGN'])->assertUnprocessable();
        $this->deleteJson("/api/v1/core/routes/{$id}")->assertOk();
        $used = Route::where('route_code', 'HAN-SGN')->firstOrFail();
        $this->deleteJson('/api/v1/core/routes/'.$used->id)->assertUnprocessable();
    }

    public function test_aircraft_conflict_turnaround_boundary_and_maintenance(): void
    {
        $payload = $this->flightPayload();
        $this->postJson('/api/v1/core/flights', $payload)->assertCreated()->assertJsonPath('data.gate_number', 'TEST-GVN-A601');
        $this->postJson('/api/v1/core/flights', $this->flightPayload('TEST02'))->assertUnprocessable();
        $this->postJson('/api/v1/core/flights', $payload)->assertUnprocessable();
        $minutes = Aircraft::findOrFail('VN-A601')->model->turnaround_time_minutes;
        $next = Carbon::parse('2030-01-01 12:00:00')->addMinutes($minutes)->format('H:i:s');
        $this->postJson('/api/v1/core/flights', $this->flightPayload('TEST03', 'VN-A601', $next, '16:00:00'))->assertCreated();
        Aircraft::findOrFail('VN-A601')->update(['operational_status' => 'MAINTENANCE']);
        $this->postJson('/api/v1/core/flights', $this->flightPayload('TEST04', 'VN-A601', '10:00:00', '12:00:00', '2030-01-02'))->assertUnprocessable();
    }

    public function test_crew_assignment_rank_conflict_and_removal(): void
    {
        $first = $this->postJson('/api/v1/core/flights', $this->flightPayload())->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/core/flights', $this->flightPayload('TEST02', 'VN-A602'))->assertCreated()->json('data.id');
        $captain = CrewMember::where('rank', 'CAPTAIN')->firstOrFail();
        $attendant = CrewMember::where('rank', 'FLIGHT_ATTENDANT')->firstOrFail();
        $this->postJson("/api/v1/core/flights/{$first}/crew/assign", ['crew_id' => $attendant->id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        $assignment = $this->postJson("/api/v1/core/flights/{$first}/crew/assign", ['crew_id' => $captain->id, 'assigned_position' => 'CAPTAIN'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/core/flights/{$second}/crew/assign", ['crew_id' => $captain->id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        $this->deleteJson('/api/v1/core/crew-members/'.$captain->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/core/flights/crew-assignments/'.$assignment)->assertOk();
        $this->getJson("/api/v1/core/flights/{$first}/crew")->assertOk()->assertJsonPath('data.assignments.0.status', 'REPLACED');
    }

    public function test_all_admin_pages_and_read_apis_render(): void
    {
        foreach (['dashboard', 'airports', 'routes', 'aircrafts', 'flights', 'crew', 'workflows'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
        foreach (['airports', 'routes', 'aircrafts', 'aircraft-models', 'schedules', 'flights', 'crew-members', 'aircrafts/maintenance-alerts'] as $resource) {
            $this->getJson('/api/v1/core/'.$resource)->assertOk()->assertJsonPath('success', true);
        }
    }

    public function test_web_route_edit_and_validation_redirect(): void
    {
        $id = Route::firstOrFail()->id;
        $this->from('/admin/routes')->put('/admin/routes/'.$id, ['distance_km' => 999])->assertRedirect('/admin/routes');
        $this->assertDatabaseHas('routes', ['id' => $id, 'distance_km' => 999]);
        $this->from('/admin/routes')->put('/admin/routes/'.$id, ['distance_km' => -1])->assertRedirect('/admin/routes')->assertSessionHasErrors('distance_km');
    }

    public function test_arrival_records_aircraft_usage_only_once(): void
    {
        $flight = OperationalFlight::where('flight_number', 'VN213')->firstOrFail();
        $tail = $flight->aircraft_tail;
        $before = Aircraft::findOrFail($tail);
        $hours = (float) $before->total_flight_hours;
        $cycles = $before->total_flight_cycles;
        $url = '/api/v1/core/flights/'.$flight->id.'/status';
        $dep = $flight->scheduled_dep_utc->format('Y-m-d H:i:s');
        $arr = $flight->scheduled_dep_utc->copy()->addHours(2)->format('Y-m-d H:i:s');
        $this->putJson($url, ['flight_status' => 'ARRIVED'])->assertUnprocessable();
        $this->putJson($url, ['flight_status' => 'BOARDING'])->assertOk();
        $this->putJson($url, ['flight_status' => 'DEPARTED', 'actual_dep_utc' => $dep])->assertOk();
        $this->putJson($url, ['flight_status' => 'ARRIVED', 'actual_arr_utc' => $arr])->assertOk();
        $this->putJson($url, ['flight_status' => 'ARRIVED', 'actual_arr_utc' => $arr])->assertOk();
        $this->putJson($url, ['flight_status' => 'SCHEDULED'])->assertUnprocessable();
        $after = Aircraft::findOrFail($tail);
        $this->assertEquals($hours + 2, (float) $after->total_flight_hours);
        $this->assertEquals($cycles + 1, $after->total_flight_cycles);
    }

    private function flightPayload(string $number = 'TEST01', string $tail = 'VN-A601', string $dep = '10:00:00', string $arr = '12:00:00', string $date = '2030-01-01'): array
    {
        $gate = AirportResource::firstOrCreate(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'TEST-G'.$tail], ['status' => 'AVAILABLE']);
        $schedule = FlightSchedule::firstOrCreate(['flight_number' => $number], [
            'route_id' => Route::where('route_code', 'HAN-SGN')->firstOrFail()->id, 'model_id' => Aircraft::findOrFail($tail)->model_id,
            'aircraft_tail' => $tail, 'resource_id' => $gate->id, 'departure_time_utc' => $dep, 'arrival_time_utc' => $arr,
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'effective_from' => $date, 'effective_to' => $date, 'status' => 'PUBLISHED',
        ]);

        return ['schedule_id' => $schedule->id, 'flight_date' => $date];
    }
}
