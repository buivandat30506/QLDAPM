<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use App\Services\Core\AuditService;
use App\Services\Core\CrewService;
use App\Services\Core\FlightEventService;
use App\Services\Core\FlightManagementService;
use App\Services\Core\MaintenanceService;
use App\Services\Core\MasterDataService;
use App\Services\Core\ScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DiagramDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is restricted to local/testing.');
        }
        DB::transaction(function () {
            $today = Carbon::today('UTC');
            $a321 = AircraftModel::where('model_code', 'A321-200')->firstOrFail();
            $b787 = AircraftModel::where('model_code', 'B787-9')->firstOrFail();
            $modelIds = [$a321->id, $b787->id];
            $accounts = ['data_demo' => 'DATA_ADMIN', 'ops_demo' => 'FLIGHT_OPS', 'fleet_demo' => 'FLEET_MANAGER',
                'tech_demo' => 'TECHNICIAN', 'crew_ops_demo' => 'CREW_OPS', 'captain_demo' => 'CREW_MEMBER'];
            foreach ($accounts as $username => $roleCode) {
                $role = Role::firstOrCreate(['role_code' => $roleCode], ['role_name' => $roleCode]);
                $user = User::firstOrCreate(['username' => $username], ['email' => $username.'@example.test',
                    'full_name' => 'Demo '.$roleCode, 'password' => Hash::make(app()->environment('testing') ? 'Demo@12345' : \Illuminate\Support\Str::password(24)), 'status' => 'ACTIVE']);
                $user->roles()->syncWithoutDetaching([$role->id]);
                DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $roleCode === 'CREW_MEMBER' ? 'self' : 'global', 'scope_id' => $roleCode === 'CREW_MEMBER' ? $user->id : '*']);
            }
            // Fill missing legacy demo metadata, without replacing user-edited values.
            foreach (CrewMember::where('crew_code', 'like', 'CRW-%')->get() as $crew) {
                $values = [];
                foreach (['license_expires_on' => '2035-12-31', 'training_expires_on' => '2035-12-31', 'qualified_model_ids' => $modelIds] as $key => $value) {
                    if ($crew->{$key} === null) {
                        $values[$key] = $value;
                    }
                }
                if ($values) {
                    $crew->update($values);
                }
            }
            foreach (['VN-A601' => [3450.5, 3000, 1500], 'VN-A861' => [5120, 3000, 1500]] as $tail => [$opening, $baseHours, $baseCycles]) {
                $aircraft = Aircraft::find($tail);
                if ($aircraft && (float) $aircraft->total_flight_hours === (float) $opening && $aircraft->total_flight_cycles === 0
                    && ! DB::table('aircraft_usage_records')->where('aircraft_tail', $tail)->exists()) {
                    $aircraft->update(['total_flight_cycles' => $tail === 'VN-A601' ? 1720 : 2100,
                        'opening_flight_cycles' => $tail === 'VN-A601' ? 1720 : 2100]);
                }
                if ($aircraft && (float) $aircraft->total_flight_hours === (float) $opening && (float) $aircraft->maintenance_base_hours === 0.0
                    && ! DB::table('aircraft_usage_records')->where('aircraft_tail', $tail)->exists()) {
                    $aircraft->update(['maintenance_base_hours' => min($baseHours, (float) $aircraft->total_flight_hours),
                        'maintenance_base_cycles' => min($baseCycles, $aircraft->total_flight_cycles)]);
                }
            }
            foreach (FlightSchedule::where('flight_number', 'like', 'VN%')->whereNull('aircraft_tail')->get() as $schedule) {
                $flight = OperationalFlight::where('schedule_id', $schedule->id)->first();
                if (! $flight) {
                    continue;
                }
                $resource = AirportResource::where('airport_code', $schedule->route->origin_airport_code)->where('resource_type', 'GATE')->first();
                if (! $resource) {
                    continue;
                }
                $schedule->update(['aircraft_tail' => $flight->aircraft_tail, 'resource_id' => $resource->id,
                    'effective_from' => $today->copy()->subDays(7)->toDateString(), 'effective_to' => $today->copy()->addDays(30)->toDateString(), 'version' => 1]);
                DB::table('entity_versions')->insertOrIgnore(['entity_type' => 'flight_schedules', 'entity_id' => $schedule->id,
                    'version' => 1, 'snapshot' => json_encode($schedule->toArray()), 'created_at' => now('UTC')]);
                OperationalFlight::where('schedule_id', $schedule->id)->whereNull('resource_id')->update(['resource_id' => $resource->id, 'gate_number' => $resource->resource_code]);
            }
            FlightCrewAssignment::where('status', 'CONFIRMED')->whereNull('confirmed_at')->update(['approved_at' => now('UTC'), 'confirmed_at' => now('UTC')]);

            $gates = [];
            foreach (['HAN', 'SGN', 'DAD'] as $airport) {
                foreach ([1, 2, 3] as $number) {
                    $gates[$airport.$number] = AirportResource::firstOrCreate(['airport_code' => $airport, 'resource_type' => 'GATE', 'resource_code' => 'DEMO-G'.$number], ['status' => 'AVAILABLE']);
                }
            }
            foreach (['VN-D101', 'VN-D102', 'VN-D103', 'VN-D104', 'VN-D201', 'VN-D202', 'VN-D203'] as $index => $tail) {
                Aircraft::firstOrCreate(['tail_number' => $tail], ['model_id' => $a321->id, 'manufacture_year' => 2020 + ($index % 5),
                    'total_flight_hours' => $index >= 4 ? 2990 : 850 + $index * 100, 'total_flight_cycles' => 400 + $index * 50,
                    'opening_flight_hours' => $index >= 4 ? 2990 : 850 + $index * 100, 'opening_flight_cycles' => 400 + $index * 50,
                    'opening_reason' => 'Số dư tổng hợp của bộ dữ liệu demo local/testing.',
                    'operational_status' => $index >= 4 ? 'MAINTENANCE' : 'OPERATIONAL']);
            }
            $crewNames = ['Nguyễn Minh An', 'Trần Hải Bình', 'Lê Thu Chi', 'Phạm Đức Dũng', 'Vũ Ngọc Hà', 'Đỗ Thanh Huy', 'Bùi Khánh Linh', 'Hoàng Quốc Nam'];
            $crewRows = [];
            foreach ($crewNames as $index => $name) {
                $code = 'DEMO-CRW-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $crewRows[] = CrewMember::firstOrCreate(['crew_code' => $code], ['full_name' => $name,
                    'email' => strtolower($code).'@example.test', 'phone' => 'DEMO-'.($index + 1),
                    'rank' => $index % 2 === 0 ? 'CAPTAIN' : 'FIRST_OFFICER', 'base_airport_code' => 'HAN',
                    'license_number' => 'SIM-LIC-'.($index + 1), 'status' => $index === 7 ? 'LEAVE' : 'AVAILABLE',
                    'license_expires_on' => $index === 6 ? $today->copy()->subDay()->toDateString() : '2035-12-31',
                    'training_expires_on' => '2035-12-31', 'qualified_model_ids' => [$a321->id], 'total_flight_hours' => 600 + $index * 80]);
            }
            $crewRows[0]->update(['user_id' => User::where('username', 'captain_demo')->value('id')]);

            $specs = [
                ['DM101', 'HAN-SGN', 'VN-D101', '02:00:00', '04:10:00', 'HAN1', $today],
                ['DM102', 'SGN-HAN', 'VN-D101', '06:00:00', '08:10:00', 'SGN1', $today],
                ['DM103', 'HAN-DAD', 'VN-D102', '03:00:00', '04:20:00', 'HAN2', $today],
                ['DM104', 'DAD-SGN', 'VN-D102', '06:00:00', '07:15:00', 'DAD1', $today],
                ['DM105', 'HAN-DAD', 'VN-D103', '00:30:00', '01:50:00', 'HAN3', $today->copy()->subDay()],
            ];
            $flights = [];
            foreach ($specs as [$number, $routeCode, $tail, $dep, $arr, $gate, $day]) {
                // Separate daily demo schedules keep repeat seeding safe after a previous demo is changed.
                $route = Route::where('route_code', $routeCode)->firstOrFail();
                $schedule = FlightSchedule::where('flight_number', $number)->where('effective_from', $day->toDateString())->first();
                if (! $schedule) {
                    $schedule = app(ScheduleService::class)->save(['flight_number' => $number, 'route_id' => $route->id, 'model_id' => $a321->id,
                        'departure_time_utc' => $dep, 'arrival_time_utc' => $arr, 'days_of_week' => [$day->dayOfWeekIso],
                        'aircraft_tail' => $tail, 'resource_id' => $gates[$gate]->id, 'effective_from' => $day->toDateString(), 'effective_to' => $day->toDateString()]);
                    foreach (['submit', 'approve', 'publish'] as $action) {
                        app(ScheduleService::class)->transition($schedule->id, $action);
                    }
                }
                $flight = OperationalFlight::where('flight_number', $number)->whereDate('flight_date', $day)->first();
                if (! $flight) {
                    $flight = app(FlightManagementService::class)->createOperationalFlight(['schedule_id' => $schedule->id, 'flight_date' => $day->toDateString()]);
                }
                $flights[$number] = $flight;
            }
            foreach (['DM101' => [0, 1], 'DM103' => [2, 3], 'DM105' => [4, 5]] as $number => $indices) {
                foreach ($indices as $index) {
                    if (! FlightCrewAssignment::where('flight_id', $flights[$number]->id)->where('crew_id', $crewRows[$index]->id)->exists()) {
                        app(CrewService::class)->assignCrewToFlight($flights[$number]->id, ['crew_id' => $crewRows[$index]->id,
                            'assigned_position' => $index % 2 === 0 ? 'CAPTAIN' : 'FIRST_OFFICER', 'notes' => 'Dữ liệu giả lập phục vụ demo']);
                    }
                }
                if ($number !== 'DM103' && $flights[$number]->flight_status === 'SCHEDULED') {
                    app(CrewService::class)->approvePlan($flights[$number]->id);
                    // Simulated member acknowledgement for demo fixtures only, with explicit audit trail.
                    foreach (FlightCrewAssignment::where('flight_id', $flights[$number]->id)->where('status', 'APPROVED')->get() as $assignment) {
                        $assignment->update(['status' => 'CONFIRMED', 'confirmed_at' => now('UTC')]);
                        DB::table('crew_notifications')->where('assignment_id', $assignment->id)->update(['read_at' => now('UTC')]);
                        AuditService::record('flight_crew_assignments', $assignment->id, 'DEMO_MEMBER_ACKNOWLEDGED');
                    }
                }
            }
            if ($flights['DM103']->flight_status === 'SCHEDULED') {
                app(FlightManagementService::class)->updateFlightStatus($flights['DM103']->id, ['flight_status' => 'BOARDING']);
            }
            if ($flights['DM105']->flight_status === 'SCHEDULED') {
                $service = app(FlightManagementService::class);
                $service->updateFlightStatus($flights['DM105']->id, ['flight_status' => 'BOARDING']);
                $service->updateFlightStatus($flights['DM105']->id, ['flight_status' => 'DEPARTED', 'actual_dep_utc' => $flights['DM105']->scheduled_dep_utc->format('Y-m-d H:i:s')]);
                $service->updateFlightStatus($flights['DM105']->id, ['flight_status' => 'ARRIVED', 'actual_arr_utc' => $flights['DM105']->scheduled_arr_utc->format('Y-m-d H:i:s')]);
            }
            app(FlightEventService::class)->receive(['source' => 'DEMO', 'external_id' => 'weather-'.$today->toDateString(), 'flight_id' => $flights['DM104']->id,
                'flight_status' => 'DELAYED', 'reason' => 'Mưa dông giả lập tại Đà Nẵng, cần quyết định điều hành.']);
            foreach (['DM901' => null, 'DM902' => $gates['HAN1']->id] as $number => $resourceId) {
                if (! FlightSchedule::where('flight_number', $number)->where('effective_from', $today->toDateString())->exists()) {
                    app(ScheduleService::class)->save(['flight_number' => $number, 'route_id' => Route::where('route_code', 'HAN-SGN')->value('id'),
                        'model_id' => $a321->id, 'departure_time_utc' => '02:30:00', 'arrival_time_utc' => '04:40:00',
                        'days_of_week' => [$today->dayOfWeekIso], 'aircraft_tail' => 'VN-D101', 'resource_id' => $resourceId,
                        'effective_from' => $today->toDateString(), 'effective_to' => $today->toDateString()]);
                }
            }
            foreach (['ECONOMY' => 'PUBLISHED', 'BUSINESS' => 'PENDING'] as $code => $status) {
                if (! DB::table('master_data_records')->where('category', 'CABIN')->where('code', $code)->exists()) {
                    $master = app(MasterDataService::class)->save(['category' => 'CABIN', 'code' => $code, 'payload' => ['name' => $code === 'ECONOMY' ? 'Phổ thông' : 'Thương gia', 'demo' => true]]);
                    foreach ($status === 'PUBLISHED' ? ['submit', 'approve', 'publish'] : ['submit'] as $action) {
                        app(MasterDataService::class)->transition($master->id, $action);
                    }
                }
            }
            foreach (['VN-D201' => true, 'VN-D202' => false] as $tail => $materials) {
                $code = 'DEMO-MX-'.$tail;
                if (! DB::table('maintenance_orders')->where('order_code', $code)->exists()) {
                    $order = app(MaintenanceService::class)->plan(['order_code' => $code, 'aircraft_tail' => $tail, 'task' => 'Kiểm tra kỹ thuật định kỳ giả lập',
                        'starts_at_utc' => now('UTC')->subHours(2)->format('Y-m-d H:i:s'), 'ends_at_utc' => now('UTC')->addHours(2)->format('Y-m-d H:i:s'),
                        'due_at_utc' => now('UTC')->addDays(2)->format('Y-m-d H:i:s')]);
                    app(MaintenanceService::class)->act($order->id, 'start', ['technician' => $materials ? 'DEMO-TECH-01' : 'DEMO-TECH-02', 'location' => $materials ? 'DEMO-HANGAR-A' : 'DEMO-HANGAR-B', 'materials_ready' => $materials]);
                }
            }
        });
        $this->command?->info('Diagram demo ready: DM101–DM105, DM901/DM902, demo crew, maintenance and role accounts.');
    }
}
