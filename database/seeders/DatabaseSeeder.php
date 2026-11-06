<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\Airport;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial airline data.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is restricted to local/testing. Use AccessControlSeeder for production roles only.');
        }
        $this->call(AccessControlSeeder::class);
        // =====================================================================
        // 1. Phân quyền & Người dùng (Roles & Users)
        // =====================================================================
        $roles = [
            ['role_code' => 'ADMIN', 'role_name' => 'Quản trị hệ thống (System Administrator)'],
            ['role_code' => 'FLIGHT_OPS', 'role_name' => 'Điều phối khai thác bay (Flight Operations Dispatcher)'],
            ['role_code' => 'AIRPORT_STAFF', 'role_name' => 'Nhân viên dịch vụ mặt đất (Ground Staff)'],
            ['role_code' => 'CUSTOMER', 'role_name' => 'Hành khách / Khách hàng (Customer)'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['role_code' => $r['role_code']], $r);
        }

        $adminRole = Role::where('role_code', 'ADMIN')->first();
        $customerRole = Role::where('role_code', 'CUSTOMER')->first();

        $adminUser = User::firstOrCreate(
            ['username' => 'toan_lead'],
            [
                'email'     => 'toan.lead@smart-airline.vn',
                'password'  => Hash::make(app()->environment('testing') ? 'Password@123' : \Illuminate\Support\Str::password(24)),
                'full_name' => 'Toàn Backend Lead',
                'status'    => 'ACTIVE',
            ]
        );
        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        // Local multi-role operator; ADMIN alone never bypasses domain permissions.
        $demoRoles = Role::whereIn('role_code', ['ADMIN', 'DATA_ADMIN', 'FLIGHT_OPS', 'FLEET_MANAGER', 'TECHNICIAN', 'CREW_OPS'])->get();
        $adminUser->roles()->syncWithoutDetaching($demoRoles->pluck('id')->all());
        foreach ($demoRoles as $role) {
            \Illuminate\Support\Facades\DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $adminUser->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => '*']);
        }

        $demoCustomer = User::firstOrCreate(
            ['username' => 'nguyen_van_a'],
            [
                'email'     => 'nguyenvana@gmail.com',
                'password'  => Hash::make(app()->environment('testing') ? 'Customer@123' : \Illuminate\Support\Str::password(24)),
                'full_name' => 'Nguyễn Văn A',
                'status'    => 'ACTIVE',
            ]
        );
        $demoCustomer->roles()->syncWithoutDetaching([$customerRole->id]);
        \Illuminate\Support\Facades\DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $demoCustomer->id, 'role_id' => $customerRole->id, 'scope_type' => 'self', 'scope_id' => $demoCustomer->id]);

        // =====================================================================
        // 2. Cụm 1: 5 Sân bay mẫu (HAN, SGN, DAD, CXR, PQC) & Tài nguyên sân bay
        // =====================================================================
        $airports = [
            [
                'iata_code'       => 'HAN',
                'icao_code'       => 'VVNB',
                'name'            => 'Cảng hàng không quốc tế Nội Bài',
                'city'            => 'Hà Nội',
                'country'         => 'Vietnam',
                'timezone'        => 'Asia/Ho_Chi_Minh',
                'operating_hours' => '24/7',
                'status'          => 'ACTIVE',
            ],
            [
                'iata_code'       => 'SGN',
                'icao_code'       => 'VVTS',
                'name'            => 'Cảng hàng không quốc tế Tân Sơn Nhất',
                'city'            => 'TP. Hồ Chí Minh',
                'country'         => 'Vietnam',
                'timezone'        => 'Asia/Ho_Chi_Minh',
                'operating_hours' => '24/7',
                'status'          => 'ACTIVE',
            ],
            [
                'iata_code'       => 'DAD',
                'icao_code'       => 'VVDN',
                'name'            => 'Cảng hàng không quốc tế Đà Nẵng',
                'city'            => 'Đà Nẵng',
                'country'         => 'Vietnam',
                'timezone'        => 'Asia/Ho_Chi_Minh',
                'operating_hours' => '24/7',
                'status'          => 'ACTIVE',
            ],
            [
                'iata_code'       => 'CXR',
                'icao_code'       => 'VVCR',
                'name'            => 'Cảng hàng không quốc tế Cam Ranh',
                'city'            => 'Khánh Hòa',
                'country'         => 'Vietnam',
                'timezone'        => 'Asia/Ho_Chi_Minh',
                'operating_hours' => '05:00 - 23:30',
                'status'          => 'ACTIVE',
            ],
            [
                'iata_code'       => 'PQC',
                'icao_code'       => 'VVPQ',
                'name'            => 'Cảng hàng không quốc tế Phú Quốc',
                'city'            => 'Kiên Giang',
                'country'         => 'Vietnam',
                'timezone'        => 'Asia/Ho_Chi_Minh',
                'operating_hours' => '06:00 - 23:00',
                'status'          => 'ACTIVE',
            ],
        ];

        foreach ($airports as $ap) {
            Airport::firstOrCreate(['iata_code' => $ap['iata_code']], $ap);
        }

        // Tài nguyên sân bay mẫu (UC-02)
        $resources = [
            ['airport_code' => 'HAN', 'resource_type' => 'TERMINAL', 'resource_code' => 'T1 Quốc nội', 'status' => 'AVAILABLE'],
            ['airport_code' => 'HAN', 'resource_type' => 'TERMINAL', 'resource_code' => 'T2 Quốc tế', 'status' => 'AVAILABLE'],
            ['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'Gate 01', 'status' => 'AVAILABLE'],
            ['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'Gate 02', 'status' => 'OCCUPIED'],
            ['airport_code' => 'HAN', 'resource_type' => 'RUNWAY', 'resource_code' => 'Runway 11L/29R', 'status' => 'AVAILABLE'],
            ['airport_code' => 'HAN', 'resource_type' => 'BAGGAGE_CAROUSEL', 'resource_code' => 'Carousel 01', 'status' => 'AVAILABLE'],

            ['airport_code' => 'SGN', 'resource_type' => 'TERMINAL', 'resource_code' => 'T1 Quốc nội', 'status' => 'AVAILABLE'],
            ['airport_code' => 'SGN', 'resource_type' => 'TERMINAL', 'resource_code' => 'T2 Quốc tế', 'status' => 'AVAILABLE'],
            ['airport_code' => 'SGN', 'resource_type' => 'GATE', 'resource_code' => 'Gate 10', 'status' => 'AVAILABLE'],
            ['airport_code' => 'SGN', 'resource_type' => 'GATE', 'resource_code' => 'Gate 15', 'status' => 'AVAILABLE'],
            ['airport_code' => 'SGN', 'resource_type' => 'RUNWAY', 'resource_code' => 'Runway 25L/07R', 'status' => 'AVAILABLE'],
            ['airport_code' => 'SGN', 'resource_type' => 'BAGGAGE_CAROUSEL', 'resource_code' => 'Carousel 02', 'status' => 'AVAILABLE'],

            ['airport_code' => 'DAD', 'resource_type' => 'TERMINAL', 'resource_code' => 'T1 Quốc nội', 'status' => 'AVAILABLE'],
            ['airport_code' => 'DAD', 'resource_type' => 'GATE', 'resource_code' => 'Gate 03', 'status' => 'AVAILABLE'],
            ['airport_code' => 'DAD', 'resource_type' => 'RUNWAY', 'resource_code' => 'Runway 35R/17L', 'status' => 'AVAILABLE'],

            ['airport_code' => 'CXR', 'resource_type' => 'TERMINAL', 'resource_code' => 'T1 Quốc tế & Nội địa', 'status' => 'AVAILABLE'],
            ['airport_code' => 'CXR', 'resource_type' => 'GATE', 'resource_code' => 'Gate 01', 'status' => 'AVAILABLE'],

            ['airport_code' => 'PQC', 'resource_type' => 'TERMINAL', 'resource_code' => 'T1', 'status' => 'AVAILABLE'],
            ['airport_code' => 'PQC', 'resource_type' => 'GATE', 'resource_code' => 'Gate 02', 'status' => 'AVAILABLE'],
        ];

        foreach ($resources as $res) {
            AirportResource::firstOrCreate([
                'airport_code'  => $res['airport_code'],
                'resource_type' => $res['resource_type'],
                'resource_code' => $res['resource_code'],
            ], $res);
        }

        // =====================================================================
        // 3. Cụm 2: 4 Đường bay chính (Routes)
        // =====================================================================
        $routes = [
            [
                'route_code'                => 'HAN-SGN',
                'origin_airport_code'       => 'HAN',
                'dest_airport_code'         => 'SGN',
                'distance_km'               => 1160.00,
                'standard_duration_minutes' => 130, // 2h10p
                'status'                    => 'ACTIVE',
            ],
            [
                'route_code'                => 'SGN-HAN',
                'origin_airport_code'       => 'SGN',
                'dest_airport_code'         => 'HAN',
                'distance_km'               => 1160.00,
                'standard_duration_minutes' => 130,
                'status'                    => 'ACTIVE',
            ],
            [
                'route_code'                => 'HAN-DAD',
                'origin_airport_code'       => 'HAN',
                'dest_airport_code'         => 'DAD',
                'distance_km'               => 630.00,
                'standard_duration_minutes' => 80, // 1h20p
                'status'                    => 'ACTIVE',
            ],
            [
                'route_code'                => 'DAD-SGN',
                'origin_airport_code'       => 'DAD',
                'dest_airport_code'         => 'SGN',
                'distance_km'               => 605.00,
                'standard_duration_minutes' => 75, // 1h15p
                'status'                    => 'ACTIVE',
            ],
        ];

        foreach ($routes as $rt) {
            Route::firstOrCreate([
                'origin_airport_code' => $rt['origin_airport_code'],
                'dest_airport_code'   => $rt['dest_airport_code'],
            ], $rt);
        }

        $routeHanSgn = Route::where('route_code', 'HAN-SGN')->first();
        $routeSgnHan = Route::where('route_code', 'SGN-HAN')->first();
        $routeHanDad = Route::where('route_code', 'HAN-DAD')->first();
        $routeDadSgn = Route::where('route_code', 'DAD-SGN')->first();

        // =====================================================================
        // 4. Cụm 3: 2 Dòng máy bay & 4 Tàu bay thật
        // =====================================================================
        $a321Model = AircraftModel::firstOrCreate(
            ['model_code' => 'A321-200'],
            [
                'manufacturer'            => 'Airbus',
                'seat_capacity'           => 220,
                'turnaround_time_minutes' => 45, // 45 phút quay đầu tối thiểu
            ]
        );

        $b787Model = AircraftModel::firstOrCreate(
            ['model_code' => 'B787-9'],
            [
                'manufacturer'            => 'Boeing',
                'seat_capacity'           => 300,
                'turnaround_time_minutes' => 60, // 60 phút quay đầu tối thiểu
            ]
        );

        $aircrafts = [
            [
                'tail_number'         => 'VN-A601',
                'model_id'            => $a321Model->id,
                'manufacture_year'    => 2021,
                'total_flight_hours'  => 3450.50,
                'total_flight_cycles' => 1720,
                'operational_status'  => 'OPERATIONAL',
            ],
            [
                'tail_number'         => 'VN-A602',
                'model_id'            => $a321Model->id,
                'manufacture_year'    => 2022,
                'total_flight_hours'  => 1890.00,
                'total_flight_cycles' => 940,
                'operational_status'  => 'OPERATIONAL',
            ],
            [
                'tail_number'         => 'VN-A861',
                'model_id'            => $b787Model->id,
                'manufacture_year'    => 2020,
                'total_flight_hours'  => 5120.00,
                'total_flight_cycles' => 2100,
                'operational_status'  => 'OPERATIONAL',
            ],
            [
                'tail_number'         => 'VN-A862',
                'model_id'            => $b787Model->id,
                'manufacture_year'    => 2023,
                'total_flight_hours'  => 980.00,
                'total_flight_cycles' => 480,
                'operational_status'  => 'OPERATIONAL',
            ],
        ];

        foreach ($aircrafts as $ac) {
            Aircraft::firstOrCreate(['tail_number' => $ac['tail_number']], $ac + [
                'opening_flight_hours' => $ac['total_flight_hours'] ?? 0, 'opening_flight_cycles' => $ac['total_flight_cycles'] ?? 0,
                'opening_reason' => 'Số dư tổng hợp của bộ dữ liệu demo local/testing.',
            ]);
        }

        // =====================================================================
        // 5. Cụm 5: 10 Nhân sự phi hành đoàn (Crew Members)
        // =====================================================================
        $crews = [
            [
                'crew_code'          => 'CRW-001',
                'full_name'          => 'Trần Văn Hải',
                'email'              => 'tranvanhai@smart-airline.vn',
                'phone'              => '0912111222',
                'rank'               => 'CAPTAIN',
                'base_airport_code'  => 'HAN',
                'license_number'     => 'ATPL-VN-9011',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 6450.00,
            ],
            [
                'crew_code'          => 'CRW-002',
                'full_name'          => 'Nguyễn Minh Tuấn',
                'email'              => 'nguyenminhtuan@smart-airline.vn',
                'phone'              => '0912333444',
                'rank'               => 'CAPTAIN',
                'base_airport_code'  => 'SGN',
                'license_number'     => 'ATPL-VN-9012',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 7100.50,
            ],
            [
                'crew_code'          => 'CRW-003',
                'full_name'          => 'Lê Hoàng Nam',
                'email'              => 'lehoangnam@smart-airline.vn',
                'phone'              => '0912555666',
                'rank'               => 'FIRST_OFFICER',
                'base_airport_code'  => 'HAN',
                'license_number'     => 'CPL-VN-8101',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 2400.00,
            ],
            [
                'crew_code'          => 'CRW-004',
                'full_name'          => 'Phạm Quốc Hưng',
                'email'              => 'phamquochung@smart-airline.vn',
                'phone'              => '0912777888',
                'rank'               => 'FIRST_OFFICER',
                'base_airport_code'  => 'SGN',
                'license_number'     => 'CPL-VN-8102',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 1850.00,
            ],
            [
                'crew_code'          => 'CRW-005',
                'full_name'          => 'Đỗ Quỳnh Anh',
                'email'              => 'doquynhanh@smart-airline.vn',
                'phone'              => '0912999000',
                'rank'               => 'PURSER',
                'base_airport_code'  => 'HAN',
                'license_number'     => 'CC-VN-7011',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 3800.00,
            ],
            [
                'crew_code'          => 'CRW-006',
                'full_name'          => 'Vũ Thảo Linh',
                'email'              => 'vuthaolinh@smart-airline.vn',
                'phone'              => '0913111222',
                'rank'               => 'PURSER',
                'base_airport_code'  => 'SGN',
                'license_number'     => 'CC-VN-7012',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 3200.00,
            ],
            [
                'crew_code'          => 'CRW-007',
                'full_name'          => 'Nguyễn Thu Hằng',
                'email'              => 'nguyenthuhang@smart-airline.vn',
                'phone'              => '0913333444',
                'rank'               => 'FLIGHT_ATTENDANT',
                'base_airport_code'  => 'HAN',
                'license_number'     => 'CC-VN-6011',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 1100.00,
            ],
            [
                'crew_code'          => 'CRW-008',
                'full_name'          => 'Trần Mai Phương',
                'email'              => 'tranmaiphuong@smart-airline.vn',
                'phone'              => '0913555666',
                'rank'               => 'FLIGHT_ATTENDANT',
                'base_airport_code'  => 'HAN',
                'license_number'     => 'CC-VN-6012',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 950.00,
            ],
            [
                'crew_code'          => 'CRW-009',
                'full_name'          => 'Hoàng Yến Nhi',
                'email'              => 'hoangyennhi@smart-airline.vn',
                'phone'              => '0913777888',
                'rank'               => 'FLIGHT_ATTENDANT',
                'base_airport_code'  => 'SGN',
                'license_number'     => 'CC-VN-6013',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 1400.00,
            ],
            [
                'crew_code'          => 'CRW-010',
                'full_name'          => 'Bùi Thanh Hương',
                'email'              => 'buithanhhuong@smart-airline.vn',
                'phone'              => '0913999000',
                'rank'               => 'FLIGHT_ATTENDANT',
                'base_airport_code'  => 'SGN',
                'license_number'     => 'CC-VN-6014',
                'status'             => 'AVAILABLE',
                'total_flight_hours' => 820.00,
            ],
        ];

        foreach ($crews as $cr) {
            CrewMember::firstOrCreate(['crew_code' => $cr['crew_code']], $cr);
        }

        // =====================================================================
        // 6. Cụm 4: Lịch bay mẫu & 5 Chuyến bay khai thác trong ngày
        // =====================================================================
        $schedules = [
            [
                'flight_number'      => 'VN213',
                'route_id'           => $routeHanSgn->id,
                'model_id'           => $a321Model->id,
                'departure_time_utc' => '08:00:00',
                'arrival_time_utc'   => '10:10:00',
                'days_of_week'       => [1, 2, 3, 4, 5, 6, 7],
                'status'             => 'PUBLISHED',
                'is_active'          => true,
            ],
            [
                'flight_number'      => 'VN214',
                'route_id'           => $routeSgnHan->id,
                'model_id'           => $a321Model->id,
                'departure_time_utc' => '11:30:00',
                'arrival_time_utc'   => '13:40:00',
                'days_of_week'       => [1, 2, 3, 4, 5, 6, 7],
                'status'             => 'PUBLISHED',
                'is_active'          => true,
            ],
            [
                'flight_number'      => 'VN155',
                'route_id'           => $routeHanDad->id,
                'model_id'           => $a321Model->id,
                'departure_time_utc' => '07:00:00',
                'arrival_time_utc'   => '08:20:00',
                'days_of_week'       => [1, 2, 3, 4, 5, 6, 7],
                'status'             => 'PUBLISHED',
                'is_active'          => true,
            ],
            [
                'flight_number'      => 'VN156',
                'route_id'           => $routeDadSgn->id,
                'model_id'           => $a321Model->id,
                'departure_time_utc' => '09:30:00',
                'arrival_time_utc'   => '10:45:00',
                'days_of_week'       => [1, 2, 3, 4, 5, 6, 7],
                'status'             => 'PUBLISHED',
                'is_active'          => true,
            ],
            [
                'flight_number'      => 'VN220',
                'route_id'           => $routeHanSgn->id,
                'model_id'           => $b787Model->id,
                'departure_time_utc' => '14:00:00',
                'arrival_time_utc'   => '16:10:00',
                'days_of_week'       => [1, 2, 3, 4, 5, 6, 7],
                'status'             => 'PUBLISHED',
                'is_active'          => true,
            ],
        ];

        foreach ($schedules as $sc) {
            FlightSchedule::firstOrCreate(['flight_number' => $sc['flight_number']], $sc);
        }

        $schVN213 = FlightSchedule::where('flight_number', 'VN213')->first();
        $schVN214 = FlightSchedule::where('flight_number', 'VN214')->first();
        $schVN155 = FlightSchedule::where('flight_number', 'VN155')->first();
        $schVN156 = FlightSchedule::where('flight_number', 'VN156')->first();
        $schVN220 = FlightSchedule::where('flight_number', 'VN220')->first();

        // 5 Chuyến bay khai thác thực tế trong ngày hôm nay
        $today = Carbon::now('UTC')->toDateString();

        $flight1 = OperationalFlight::firstOrCreate(
            ['flight_number' => 'VN213', 'flight_date' => $today],
            [
                'schedule_id'       => $schVN213->id,
                'route_id'          => $routeHanSgn->id,
                'aircraft_tail'     => 'VN-A601',
                'scheduled_dep_utc' => Carbon::parse("{$today} 08:00:00", 'UTC'),
                'scheduled_arr_utc' => Carbon::parse("{$today} 10:10:00", 'UTC'),
                'flight_status'     => 'SCHEDULED',
                'gate_number'       => '01',
            ]
        );

        // Chuyến VN214 sau chuyến VN213 lúc 11:30 (cách 1h20p > 45p turnaround)
        $flight2 = OperationalFlight::firstOrCreate(
            ['flight_number' => 'VN214', 'flight_date' => $today],
            [
                'schedule_id'       => $schVN214->id,
                'route_id'          => $routeSgnHan->id,
                'aircraft_tail'     => 'VN-A601',
                'scheduled_dep_utc' => Carbon::parse("{$today} 11:30:00", 'UTC'),
                'scheduled_arr_utc' => Carbon::parse("{$today} 13:40:00", 'UTC'),
                'flight_status'     => 'SCHEDULED',
                'gate_number'       => '10',
            ]
        );

        $flight3 = OperationalFlight::firstOrCreate(
            ['flight_number' => 'VN155', 'flight_date' => $today],
            [
                'schedule_id'       => $schVN155->id,
                'route_id'          => $routeHanDad->id,
                'aircraft_tail'     => 'VN-A602',
                'scheduled_dep_utc' => Carbon::parse("{$today} 07:00:00", 'UTC'),
                'scheduled_arr_utc' => Carbon::parse("{$today} 08:20:00", 'UTC'),
                'flight_status'     => 'BOARDING',
                'gate_number'       => '02',
            ]
        );

        // Chuyến VN156 sau chuyến VN155 lúc 09:30 (cách 1h10p > 45p turnaround)
        $flight4 = OperationalFlight::firstOrCreate(
            ['flight_number' => 'VN156', 'flight_date' => $today],
            [
                'schedule_id'       => $schVN156->id,
                'route_id'          => $routeDadSgn->id,
                'aircraft_tail'     => 'VN-A602',
                'scheduled_dep_utc' => Carbon::parse("{$today} 09:30:00", 'UTC'),
                'scheduled_arr_utc' => Carbon::parse("{$today} 10:45:00", 'UTC'),
                'flight_status'     => 'SCHEDULED',
                'gate_number'       => '03',
            ]
        );

        $flight5 = OperationalFlight::firstOrCreate(
            ['flight_number' => 'VN220', 'flight_date' => $today],
            [
                'schedule_id'       => $schVN220->id,
                'route_id'          => $routeHanSgn->id,
                'aircraft_tail'     => 'VN-A861',
                'scheduled_dep_utc' => Carbon::parse("{$today} 14:00:00", 'UTC'),
                'scheduled_arr_utc' => Carbon::parse("{$today} 16:10:00", 'UTC'),
                'flight_status'     => 'SCHEDULED',
                'gate_number'       => '02',
            ]
        );

        // Phân công tổ bay mẫu cho chuyến VN213 (Đạt chuẩn tối thiểu: 1 Captain + 1 First Officer)
        $captain1 = CrewMember::where('crew_code', 'CRW-001')->first();
        $fo1      = CrewMember::where('crew_code', 'CRW-003')->first();
        $fa1      = CrewMember::where('crew_code', 'CRW-007')->first();

        FlightCrewAssignment::firstOrCreate(
            ['flight_id' => $flight1->id, 'crew_id' => $captain1->id],
            [
                'assigned_position' => 'CAPTAIN',
                'duty_start_utc'    => Carbon::parse("{$today} 07:00:00", 'UTC'),
                'duty_end_utc'      => Carbon::parse("{$today} 10:40:00", 'UTC'),
                'status'            => 'CONFIRMED',
                'notes'             => 'Cơ trưởng chính tuyến HAN-SGN',
            ]
        );

        FlightCrewAssignment::firstOrCreate(
            ['flight_id' => $flight1->id, 'crew_id' => $fo1->id],
            [
                'assigned_position' => 'FIRST_OFFICER',
                'duty_start_utc'    => Carbon::parse("{$today} 07:00:00", 'UTC'),
                'duty_end_utc'      => Carbon::parse("{$today} 10:40:00", 'UTC'),
                'status'            => 'CONFIRMED',
                'notes'             => 'Cơ phó tuyến HAN-SGN',
            ]
        );

        FlightCrewAssignment::firstOrCreate(
            ['flight_id' => $flight1->id, 'crew_id' => $fa1->id],
            [
                'assigned_position' => 'FLIGHT_ATTENDANT',
                'duty_start_utc'    => Carbon::parse("{$today} 07:00:00", 'UTC'),
                'duty_end_utc'      => Carbon::parse("{$today} 10:40:00", 'UTC'),
                'status'            => 'CONFIRMED',
                'notes'             => 'Tiếp viên phục vụ khoang phổ thông',
            ]
        );

        $this->call(DiagramDemoSeeder::class);
        $this->command?->info('DatabaseSeeder hoàn tất: dữ liệu nền và các kịch bản demo Activity/Sequence.');
    }
}
