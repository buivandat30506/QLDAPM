<?php

$root = dirname(__DIR__);
if (is_file($root.'/docs/testing/evidence/2026-10-18-g2/build-manifest.json')) {
    throw new RuntimeException('Historical G1 importer is frozen to preserve later progress. Use scripts/record-session-evidence.php for the G2 checkpoint.');
}
$evidence = 'docs/testing/evidence/2026-10-18';
function readCsv(string $path): array
{
    $file = fopen($path, 'r'); $header = fgetcsv($file); $rows = [];
    while (($row = fgetcsv($file)) !== false) { $rows[] = array_combine($header, $row); }
    fclose($file);

    return [$header, $rows];
}
function writeCsv(string $path, array $header, array $rows): void
{
    $file = fopen($path, 'w'); fputcsv($file, $header);
    foreach ($rows as $row) { fputcsv($file, array_map(fn ($field) => $row[$field] ?? '', $header)); }
    fclose($file);
}
$execution = [];
$build = json_decode(file_get_contents($root.'/'.$evidence.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['sqlite' => 'g1-sqlite.xml', 'mysql' => 'mysql.xml', 'browser' => 'browser.xml'] as $profile => $file) {
    $path = $root.'/'.$evidence.'/'.$file;
    if (! file_exists($path)) { continue; }
    $xml = simplexml_load_file($path);
    foreach ($xml->xpath('//testcase') as $test) {
        $status = isset($test->failure) || isset($test->error) ? 'Failed' : (isset($test->skipped) ? 'Skipped' : 'Passed');
        $execution[] = ['id' => (string) ($test['class'] ?: $test['classname']).'::'.(string) $test['name'], 'profile' => $profile,
            'actual_result' => $status === 'Passed' ? 'JUnit: executed, no failure/error' : trim((string) ($test->failure ?: $test->error ?: $test->skipped)),
            'status' => $status, 'build_sha256' => $build['build_sha256'], 'base_commit' => $build['base_commit'],
            'executed_at_utc' => gmdate('c', filemtime($path)), 'duration_seconds' => (string) $test['time'], 'evidence_path' => $evidence.'/'.$file, 'bug_id' => ''];
    }
}
writeCsv($root.'/docs/testing/test-execution-results.csv', ['id', 'profile', 'actual_result', 'status', 'build_sha256', 'base_commit', 'executed_at_utc', 'duration_seconds', 'evidence_path', 'bug_id'], $execution);
$aliases = [
    'UT-W1-LOGIN-01' => ['mysql', 'test_login_roles_and_token_revocation'],
    'UT-W1-FLIGHT-01' => ['mysql', 'test_aircraft_conflict_turnaround_boundary_and_maintenance'],
    'UT-W1-FLIGHT-02' => ['mysql', 'test_ut_w1_w2_flight_02_rejects_arrival_not_after_departure'],
    'UT-W1-BOOK-01' => ['mysql', 'test_two_connections_compete_for_last_seat_without_overbooking'],
    'UT-W2-AUTH-01' => ['mysql', 'test_login_roles_and_token_revocation'],
    'UT-W2-AUTH-02' => ['mysql', 'test_admin_cannot_release_and_role_without_scope_cannot_write'],
    'UT-W2-AIRPORT-01' => ['mysql', 'test_airport_crud_and_validation'],
    'UT-W2-ROUTE-01' => ['mysql', 'test_route_crud_and_duplicate_constraints'],
    'UT-W2-FLIGHT-01' => ['mysql', 'test_aircraft_conflict_turnaround_boundary_and_maintenance'],
    'UT-W2-FLIGHT-02' => ['mysql', 'test_ut_w1_w2_flight_02_rejects_arrival_not_after_departure'],
    'UT-W2-FLIGHT-03' => ['mysql', 'test_aircraft_conflict_turnaround_boundary_and_maintenance'],
    'UT-W2-CREW-01' => ['mysql', 'test_crew_license_availability_rest_and_replacement_rollback'],
    'UT-W2-BOOK-01' => ['mysql', 'test_two_connections_compete_for_last_seat_without_overbooking'],
    'UT-W2-MAINT-02' => ['mysql', 'test_maintenance_requires_resources_completion_approval_and_non_overdue_release'],
    'UT-W2-AUDIT-01' => ['mysql', 'test_audit_redacts_nested_pii_and_has_correlation'],
    'UAT-W1-01' => ['browser', 'UAT-UC45-G1:'],
    'UAT-W2-01' => ['browser', 'UAT-UC45-G1:'],
    'UAT-W2-06' => ['browser', 'UAT-UC18-19:'],
];
$prompt = file_get_contents($root.'/CODEX_MASTER_PROMPT_QL_HANG_KHONG.md');
$unitSection = explode('### C.2 Acceptance Test/UAT', explode('### C.1 Unit Test', $prompt)[1])[0];
$uatSection = explode('## 17.', explode('### C.2 Acceptance Test/UAT', $prompt)[1])[0];
$baseline = [];
foreach (['UT' => [$unitSection, 4], 'UAT' => [$uatSection, 4]] as $kind => [$section, $firstWeekCount]) {
    preg_match_all('/^'.$kind.'-[^\r\n]+/m', $section, $matches);
    foreach ($matches[0] as $index => $line) {
        $columns = array_map('trim', explode('|', $line));
        $week = $index < $firstWeekCount ? 'W1' : 'W2';
        $id = $kind.'-'.$week.'-'.substr($columns[0], strlen($kind) + 1);
        $alias = $aliases[$id] ?? null;
        $matched = $alias ? array_values(array_filter($execution, fn ($test) => $test['profile'] === $alias[0] && str_contains($test['id'], $alias[1]))) : [];
        $statuses = array_column($matched, 'status');
        $status = ! $matched ? 'Not Run' : (in_array('Failed', $statuses) ? 'Failed' : (in_array('Skipped', $statuses) ? 'Skipped' : 'Passed'));
        $baseline[] = ['id' => $id, 'source_id' => $columns[0], 'week' => $week, 'objective' => $columns[$kind === 'UT' ? 2 : ($week === 'W1' ? 2 : 1)],
            'expected_result' => $kind === 'UT' ? $columns[4] : $columns[$week === 'W1' ? 3 : 2],
            'actual_test_type' => $alias ? ($alias[0] === 'browser' ? 'Browser acceptance; approval Pending' : ($id === 'UT-W1-BOOK-01' || $id === 'UT-W2-BOOK-01' ? 'MySQL integration race; capacity hold, seat assignment deferred to check-in' : 'Feature/DB; source label UT preserved')) : 'Not implemented/executed',
            'profile' => $alias[0] ?? '', 'implementation' => $alias[1] ?? '', 'status' => $status,
            'evidence_path' => implode(';', array_unique(array_column($matched, 'evidence_path'))), 'approval' => $kind === 'UAT' ? 'Pending' : 'N/A'];
    }
}
if (count($baseline) !== 43) { throw new RuntimeException('Expected 24 unit baseline + 19 UAT baseline.'); }
writeCsv($root.'/docs/testing/baseline-mapping.csv', ['id', 'source_id', 'week', 'objective', 'expected_result', 'actual_test_type', 'profile', 'implementation', 'status', 'evidence_path', 'approval'], $baseline);
[$caseHeader, $cases] = readCsv($root.'/docs/testing/test-cases.csv');
$caseIds = array_column($cases, 'id');
foreach ($baseline as $case) {
    if (! in_array($case['id'], $caseIds, true)) {
        $cases[] = ['id' => $case['id'], 'source_id' => $case['week'].' '.$case['source_id'], 'uc_id' => '', 'actor' => 'Theo phụ lục C', 'objective' => $case['objective'], 'preconditions' => 'Synthetic test fixture, isolated DB', 'input_steps' => 'Xem phương thức test hoặc kế hoạch baseline', 'expected_result' => $case['expected_result'], 'status' => $case['status'], 'implementation' => $case['implementation'], 'approval' => $case['approval']];
    } else {
        foreach ($cases as &$existing) { if ($existing['id'] === $case['id']) { $existing['status'] = $case['status']; $existing['implementation'] = $case['implementation']; } } unset($existing);
    }
}
writeCsv($root.'/docs/testing/test-cases.csv', $caseHeader, $cases);
$additional = [
    'UAT-UC45-G1' => ['UC-45', 'UAT-UC45-G1:', '1. Admin đăng nhập. 2. Tạo user. 3. Gán CUSTOMER/self. 4. User đăng nhập. 5. Truy cập audit bị 403.', 'User và scope lưu DB; thiếu quyền bị từ chối; approval Pending.'],
    'UAT-UC46-G1' => ['UC-46', 'UAT-UC46-G1:', '1. Đăng nhập. 2. Lọc LOGIN_SUCCEEDED. 3. Tải CSV. 4. POST thiếu CSRF.', 'Đúng filter/export; POST thiếu token bị 419.'],
    'UAT-UC18-19' => ['UC-18;UC-19', 'UAT-UC18-19:', '1. Tạo hồ sơ và consent. 2. Sửa hồ sơ. 3. Rút consent. 4. Gửi hỗ trợ. 5. Sửa và hủy.', 'Hồ sơ và lịch sử consent lưu thật; hỗ trợ chuyển bộ phận, cập nhật và kết thúc đúng trạng thái.'],
];
$sourceAliases = [
    'UAT-UC18-MAIN' => 'UAT-UC18-19:', 'UAT-UC18-ALT' => 'UAT-UC18-19:',
    'UAT-UC19-MAIN' => 'UAT-UC18-19:', 'UAT-UC19-ALT' => 'UAT-UC18-19:',
    'UAT-UC45-MAIN' => 'UAT-UC45-G1:', 'UAT-UC46-MAIN' => 'UAT-UC46-G1:',
    'UAT-UC46-ALT' => 'UAT-UC46-G1:', 'UAT-UC46-EXC' => 'UAT-UC45-G1:',
];
foreach ($additional as $id => [$uc, $alias, $steps, $expected]) {
    if (! in_array($id, array_column($cases, 'id'), true)) {
        $cases[] = ['id' => $id, 'source_id' => 'DERIVED browser slice', 'uc_id' => $uc, 'actor' => 'Quản trị / hành khách với tài khoản tổng hợp', 'objective' => $id, 'preconditions' => 'prepare-browser synthetic MySQL database; local built assets', 'input_steps' => $steps, 'expected_result' => $expected, 'status' => 'Not Run', 'implementation' => '', 'approval' => 'Pending'];
    }
    $sourceAliases[$id] = $alias;
}
foreach (['actual_result', 'evidence_path', 'profile', 'build_sha256', 'tester', 'executed_at_utc'] as $field) { if (! in_array($field, $caseHeader, true)) { $caseHeader[] = $field; } }
foreach ($cases as &$case) {
    foreach ($baseline as $baselineCase) {
        if ($baselineCase['id'] !== $case['id']) { continue; }
        $case['profile'] = $baselineCase['profile']; $case['evidence_path'] = $baselineCase['evidence_path'];
        if ($baselineCase['status'] !== 'Not Run') {
            $case['build_sha256'] = $build['build_sha256']; $case['tester'] = 'Codex automated '.$baselineCase['actual_test_type'];
            $case['actual_result'] = 'Mapped to executed JUnit test: '.$baselineCase['implementation'];
        }
    }
    $alias = $sourceAliases[$case['id']] ?? null;
    if (! $alias) { continue; }
    $matched = array_values(array_filter($execution, fn ($test) => $test['profile'] === 'browser' && str_contains($test['id'], $alias)));
    if (! $matched) { continue; }
    $case['status'] = in_array('Failed', array_column($matched, 'status')) ? 'Failed' : (in_array('Skipped', array_column($matched, 'status')) ? 'Skipped' : 'Passed');
    $case['implementation'] = $alias; $case['evidence_path'] = $evidence.'/browser.xml'; $case['profile'] = 'Chrome/MySQL, synthetic fixtures';
    $case['build_sha256'] = $build['build_sha256']; $case['tester'] = 'Codex automated browser';
    $case['executed_at_utc'] = $matched[0]['executed_at_utc']; $case['actual_result'] = $matched[0]['actual_result'];
    $case['approval'] = 'Pending';
} unset($case);
writeCsv($root.'/docs/testing/test-cases.csv', $caseHeader, $cases);
$counts = [];
foreach ($execution as $row) { $counts[$row['profile']][$row['status']] = ($counts[$row['profile']][$row['status']] ?? 0) + 1; }
echo json_encode(['execution' => $counts, 'baseline' => array_count_values(array_column($baseline, 'status')), 'approval' => 'Pending'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

[$traceHeader, $traceRows] = readCsv($root.'/docs/traceability.csv');
$links = [
    'UC-01' => ['UT-W2-AIRPORT-01', 'IATA/ICAO/timezone và mọi nhánh nguồn cần mở rộng; core scope hạn chế còn deny.'],
    'UC-03' => ['Tests\\Feature\\DiagramWorkflowTest::test_master_data_approval_versions_and_audit', 'Còn import/export, validate batch và quality report.'],
    'UC-04' => ['UT-W2-ROUTE-01', 'Còn restrictions/mùa khai thác và scope theo tài nguyên.'],
    'UC-05' => ['Tests\\Feature\\DiagramWorkflowTest::test_schedule_rechecks_resources_before_publication_and_flights_require_source', 'Còn race publish, version approvals đầy đủ.'],
    'UC-06' => ['UT-W2-FLIGHT-03', 'Còn race tài nguyên/crew/schedule trên MySQL và biên timezone.'],
    'UC-07' => ['UT-W1-FLIGHT-01;UT-W2-FLIGHT-01;UT-W1-FLIGHT-02;UT-W2-FLIGHT-02', 'Lifecycle còn CHECKIN_OPEN/CLOSED và nguồn lực G2/G4.'],
    'UC-08' => ['Tests\\Feature\\DiagramWorkflowTest::test_event_unknown_flight_is_logged_and_alert_waits_for_operator', 'Đã sửa JSON idempotency trên MySQL; adapter airport thật/outbox chưa có.'],
    'UC-09' => ['Tests\\Feature\\DiagramWorkflowTest::test_invalid_disruption_plan_rolls_back_flight_and_crew', 'Còn đủ nhánh đổi cửa/nguồn lực/thông báo và concurrency.'],
    'UC-11' => ['Tests\\Feature\\Module1Test::test_arrival_records_aircraft_usage_only_once', 'Còn ledger usage theo event ID ngoài và cấu hình ghế/version.'],
    'UC-12' => ['Tests\\Feature\\DiagramWorkflowTest::test_long_duty_and_maintenance_overlap_are_rejected', 'Còn plans theo giờ/cycle/condition, lịch sử hạn.'],
    'UC-13' => ['Tests\\Feature\\DiagramWorkflowTest::test_maintenance_requires_resources_completion_approval_and_non_overdue_release', 'Work order/material/evidence/signoff chi tiết và UUID baseline chưa đủ.'],
    'UC-14' => ['UT-W2-MAINT-02', 'Còn TechnicalRelease/history đầy đủ và nhiều blocker đồng thời.'],
    'UC-15' => ['Tests\\Feature\\DiagramWorkflowTest::test_crew_profile_from_form_has_usable_qualifications_and_checks_training_and_availability', 'Còn hồ sơ license/training/availability chi tiết và dự bị.'],
    'UC-16' => ['UT-W2-CREW-01', 'Còn race assignment và nhánh source thiếu nhân sự.'],
    'UC-17' => ['Tests\\Feature\\DiagramWorkflowTest::test_crew_plan_needs_approval_member_acknowledgement_and_ownership', 'Còn outbox notification và version/approval/history chi tiết.'],
    'UC-18' => ['Tests\\Feature\\PassengerProfileTest;UAT-W2-06', 'Policy consent local-policy-v1 là minh họa; chờ chủ nghiệp vụ duyệt và UAT ký.'],
    'UC-19' => ['Tests\\Feature\\PassengerProfileTest', 'Chuyển bộ phận là queue nội bộ thực; chưa xác nhận đối tác ngoài; UAT ký Pending.'],
    'UC-21' => ['IT-BOOK-RACE-01;UT-W1-BOOK-01;UT-W2-BOOK-01', 'Đã sửa snapshot trước khóa; còn server pricing, nhiều chặng, ancillary, expiry và payment.'],
    'UC-24' => ['Tests\\Feature\\AccessFoundationTest::test_owner_reads_but_other_passenger_and_admin_cannot_read_or_checkin', 'Chỉ mới chứng minh auth/ownership; còn seat map/capacity và check-in end-to-end.'],
    'UC-27' => ['Tests\\Feature\\AccessFoundationTest::test_legacy_browser_payment_and_boarding_cannot_claim_unverified_success', 'Legacy scanner trả 409; cần opaque QR/reissue/revoke/wrong-flight/no-show/offload.'],
    'UC-29' => ['Tests\\Feature\\AccessFoundationTest::test_airport_scope_stops_cross_airport_read_and_write', 'Mới bảo vệ read/write scope; chưa late/dedupe/segments/incidents.'],
    'UC-45' => ['UT-W1-LOGIN-01;UT-W2-AUTH-01;UT-W2-AUTH-02;Tests\\Feature\\AccessFoundationTest', 'Còn unit scope, IdP sync adapter, API service identity và full delegation races; UAT ký Pending.'],
    'UC-46' => ['UT-W2-AUDIT-01;Tests\\Feature\\AccessFoundationTest', 'Ghi/đọc/export đã có evidence; còn retention, service actor và audit toàn bộ module chưa triển khai.'],
];
foreach ($traceRows as &$row) {
    $uc = $row['uc_id'];
    if (isset($links[$uc])) {
        [$row['integration_test_ids'], $row['open_issue']] = $links[$uc];
        $row['evidence_path'] = $evidence.'/mysql.xml';
    }
    if (in_array($uc, ['UC-20', 'UC-22', 'UC-23', 'UC-41', 'UC-42', 'UC-47', 'UC-48'], true)) {
        $row['implementation_paths'] = ''; $row['ui_route'] = ''; $row['api_operation'] = '';
        $row['status'] = 'Not Implemented';
        $row['open_issue'] = 'Giữ trong phạm vi đích; chưa triển khai workflow nguồn. Legacy confirm payment không được coi là thanh toán.';
    }
    if (in_array($uc, ['UC-18', 'UC-19'], true)) {
        $row['implementation_paths'] = 'app/Services/Passenger/'.($uc === 'UC-18' ? 'PassengerProfileService.php' : 'AssistanceService.php').';app/Http/Controllers/Web/PassengerPortalController.php;tests/Feature/PassengerProfileTest.php;tests/Acceptance/passenger.spec.js';
        $row['ui_route'] = $uc === 'UC-18' ? '/portal/profiles' : '/portal/assistance';
        $row['db_entities'] = $uc === 'UC-18' ? 'passenger_profiles;passenger_consents' : 'assistance_requests;passenger_profiles;operational_flights';
        $row['status'] = 'Implemented slice; UAT approval Pending';
        $row['evidence_path'] .= ';'.$evidence.'/browser.xml';
        $row['uat_test_ids'] = implode(';', array_unique([...explode(';', $row['uat_test_ids']), 'UAT-UC18-19']));
    }
    if (in_array($uc, ['UC-45', 'UC-46'], true)) {
        $row['implementation_paths'] = $uc === 'UC-45' ? 'app/Shared/Security/AccessControl.php;app/Services/Administration/AdministrationService.php;app/Http/Controllers/AuthController.php;tests/Feature/AccessFoundationTest.php' : 'app/Services/Core/AuditService.php;app/Services/Administration/AuditQuery.php;app/Shared/Security/SensitiveData.php;tests/Feature/AccessFoundationTest.php';
        $row['ui_route'] = $uc === 'UC-45' ? '/administration/users' : '/administration/audit';
        $row['db_entities'] = $uc === 'UC-45' ? 'users;roles;user_roles;permissions;role_permissions;user_data_scopes;delegations' : 'audit_logs';
        $row['uat_test_ids'] = implode(';', array_unique([...explode(';', $row['uat_test_ids']), $uc === 'UC-45' ? 'UAT-UC45-G1' : 'UAT-UC46-G1']));
        $row['evidence_path'] .= ';'.$evidence.'/browser.xml';
    }
    if ($uc === 'UC-08') { $row['unit_test_ids'] = 'Tests\\Unit\\CanonicalJsonTest::test_object_order_and_mysql_json_whitespace_do_not_change_identity'; }
    if ($uc === 'UC-46') { $row['unit_test_ids'] = 'Tests\\Unit\\CanonicalJsonTest::test_nested_sensitive_data_redaction_keeps_business_fields'; }
} unset($row);
writeCsv($root.'/docs/traceability.csv', $traceHeader, $traceRows);
