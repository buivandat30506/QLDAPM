<?php

// G0 only: never overwrite execution status from a later implementation session.
$root = dirname(__DIR__);
$source = file_get_contents($root.'/CODEX_MASTER_PROMPT_QL_HANG_KHONG.md');
preg_match_all('/^#### (UC-\d{2}) - ([^\r\n]+)\R(.*?)(?=^#### UC-|^### M\d|^## 15\.|\z)/msu', $source, $matches, PREG_SET_ORDER);
if (count($matches) !== 48) {
    throw new RuntimeException('Expected exactly 48 source use cases.');
}
$modules = [3 => 'M01', 6 => 'M02', 9 => 'M03', 11 => 'M04', 14 => 'M05', 17 => 'M06', 19 => 'M07', 23 => 'M08', 27 => 'M09', 30 => 'M10', 33 => 'M11', 36 => 'M12', 39 => 'M13', 42 => 'M14', 44 => 'M15', 48 => 'M16'];
$paths = [
    'M01' => ['app/Services/Core/AirportService.php;app/Services/Core/MasterDataService.php', '/admin/airports;/admin/workflows', '/api/v1/core/airports;/api/v1/core/master-data', 'airports;airport_resources;master_data_records;entity_versions'],
    'M02' => ['app/Services/Core/RouteService.php;app/Services/Core/ScheduleService.php', '/admin/routes;/admin/flights;/admin/workflows', '/api/v1/core/routes;/api/v1/core/schedules', 'routes;flight_schedules;airport_resources;entity_versions'],
    'M03' => ['app/Services/Core/FlightManagementService.php;app/Services/Core/FlightEventService.php', '/admin/flights;/admin/workflows', '/api/v1/core/flights;/api/v1/core/events', 'operational_flights;flight_events'],
    'M04' => ['app/Services/Core/AircraftService.php', '/admin/aircrafts', '/api/v1/core/aircrafts', 'aircrafts;aircraft_models;aircraft_usage_records'],
    'M05' => ['app/Services/Core/MaintenanceService.php', '/admin/workflows', '/api/v1/core/maintenance', 'maintenance_orders'],
    'M06' => ['app/Services/Core/CrewService.php;app/Services/Core/CrewRulesService.php', '/admin/crew;/admin/workflows', '/api/v1/core/crew-members;/api/v1/core/crew-assignments', 'crew_members;flight_crew_assignments;crew_notifications'],
    'M07' => ['', '', '', 'passenger_profiles'],
    'M08' => ['app/Services/Passenger/BookingService.php', '', '/api/v1/passenger/bookings', 'order_bookings;order_items;passenger_profiles'],
    'M09' => ['app/Services/Passenger/CheckInService.php', '', '/api/v1/passenger/checkin', 'checkin_records;boarding_pass_tokens'],
    'M10' => ['app/Services/Passenger/BaggageService.php', '', '/api/v1/passenger/baggage', 'baggage_tags;baggage_tracking_events'],
    'M11' => ['', '', '', ''], 'M12' => ['', '', '', ''], 'M13' => ['', '', '', ''],
    'M14' => ['app/Http/Controllers/Web/AdminController.php', '/admin/dashboard', '', 'operational_flights;aircrafts'],
    'M15' => ['', '', '', ''],
    'M16' => ['app/Http/Controllers/AuthController.php;app/Http/Middleware/CoreAccess.php;app/Services/Core/AuditService.php', '/login', '/api/v1/auth/login;/api/v1/core/audit', 'users;roles;user_roles;audit_logs'],
];
$tracePath = $root.'/docs/traceability.csv';
if (file_exists($tracePath)) {
    throw new RuntimeException('Traceability already exists; update it deliberately instead of reinitializing.');
}
@mkdir($root.'/docs/testing', 0777, true);
$csv = fopen($tracePath, 'w');
$cases = fopen($root.'/docs/testing/test-cases.csv', 'w');
fputcsv($csv, explode(',', 'module_id,uc_id,source_priority,actor,br_refs,nfr_refs,implementation_paths,ui_route,api_operation,db_entities,unit_test_ids,integration_test_ids,uat_test_ids,status,evidence_path,open_issue'));
fputcsv($cases, ['id', 'source_id', 'uc_id', 'actor', 'objective', 'preconditions', 'input_steps', 'expected_result', 'status', 'implementation', 'approval']);
$requirements = [];
foreach ($matches as $match) {
    $uc = $match[1];
    $number = (int) substr($uc, 3);
    foreach ($modules as $limit => $candidate) {
        if ($number <= $limit) { $module = $candidate; break; }
    }
    preg_match_all('/\*\*([^*]+)\*\*:[ \t]*(.*?)(?=\R\*\*|\z)/su', $match[3], $fields, PREG_SET_ORDER);
    $attributes = [];
    foreach ($fields as $field) { $attributes[$field[1]] = trim($field[2]); }
    preg_match_all('/BR-\d+/', $attributes['Business Rule'] ?? '', $br);
    preg_match_all('/NFR-\d+/', $attributes['Non-Functional'] ?? '', $nfr);
    $requirements[] = ['module_id' => $module, 'uc_id' => $uc, 'name' => $match[2], 'source' => 'Master prompt appendix A', 'attributes' => $attributes];
    [$implementation, $ui, $api, $entities] = $paths[$module];
    $status = ($number <= 17 || in_array($number, [21, 24, 26, 27, 28, 29, 40, 45, 46])) ? 'Existing Partial' : 'Not Implemented';
    $uat = [];
    foreach (['MAIN' => 'Basic Flow', 'ALT' => 'Alternative Flow', 'EXC' => 'Exception Flow', 'DENIED' => null] as $suffix => $field) {
        if ($field !== null && empty($attributes[$field])) { continue; }
        $id = 'UAT-'.str_replace('-', '', $uc).'-'.$suffix;
        $uat[] = $id;
        fputcsv($cases, [$id, $field === null ? 'DERIVED' : 'Appendix A '.$field, $uc, $attributes['Actor'], $match[2].' / '.$suffix, $attributes['Pre-Condition'] ?? '', $field === null ? 'Unauthenticated; missing permission; other owner or scope; assert DB unchanged' : $attributes[$field], $field === null ? '401/403/404; no leakage or mutation' : ($attributes['Post-Condition'] ?? ''), 'Not Run', '', 'Pending']);
    }
    fputcsv($csv, [$module, $uc, $attributes['Priority'], $attributes['Actor'], implode(';', array_unique($br[0])), implode(';', array_unique($nfr[0])), $implementation, $ui, $api, $entities, '', '', implode(';', $uat), $status, '', 'Source flows require verification; see docs/repository-inventory.md']);
}
fclose($csv);
fclose($cases);
file_put_contents($root.'/docs/requirements-source.json', json_encode(['source_sha256' => hash('sha256', $source), 'use_cases' => $requirements], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo 'Initialized 48 UC with exact source priority/actors/rules and source-flow UAT cases. No tests marked Passed.'.PHP_EOL;
