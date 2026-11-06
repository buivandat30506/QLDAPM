<?php

// Import only completed executions for this checkpoint; retain all historical builds.
$root = dirname(__DIR__); $directory = 'docs/testing/evidence/2026-10-18-fleet';
$build = json_decode(file_get_contents($root.'/'.$directory.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($build['files'] as $file => $expected) {
    if (! is_file($root.'/'.$file) || hash_file('sha256', $root.'/'.$file) !== $expected) { throw new RuntimeException('Source changed since snapshot: '.$file); }
}
function resourceCsv(string $path): array {
    $f = fopen($path, 'r'); $header = fgetcsv($f, 0, ',', '"', ''); $rows = [];
    while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) { $rows[] = array_combine($header, $row); } fclose($f);
    return [$header, $rows];
}
function resourceWrite(string $path, array $header, array $rows): void {
    $f = fopen($path, 'w'); fputcsv($f, $header, ',', '"', '');
    foreach ($rows as $row) { fputcsv($f, array_map(fn ($key) => $row[$key] ?? '', $header), ',', '"', ''); } fclose($f);
}
$runs = [];
foreach (['mysql', 'sqlite', 'browser'] as $profile) {
    $filename = $directory.'/'.$profile.'.xml'; $xml = simplexml_load_file($root.'/'.$filename);
    if (! $xml || ! $xml->xpath('//testcase')) { throw new RuntimeException('Missing completed JUnit: '.$profile); }
    foreach ($xml->xpath('//testcase') as $test) {
        $status = isset($test->failure) || isset($test->error) ? 'Failed' : (isset($test->skipped) ? 'Skipped' : 'Passed'); $parent = $test->xpath('..')[0];
        $runs[] = ['id' => (string) ($test['class'] ?: $test['classname']).'::'.(string) $test['name'], 'profile' => $profile,
            'actual_result' => $status === 'Passed' ? 'JUnit: executed, no failure/error' : trim((string) ($test->failure ?: $test->error ?: $test->skipped)),
            'status' => $status, 'build_sha256' => $build['build_sha256'], 'base_commit' => $build['base_commit'],
            'executed_at_utc' => isset($parent['timestamp']) ? (string) $parent['timestamp'] : gmdate('c', filemtime($root.'/'.$filename)),
            'duration_seconds' => (string) $test['time'], 'evidence_path' => $filename, 'bug_id' => ''];
    }
}
foreach (['restore.json', 'local-demo.json'] as $filename) {
    $report = json_decode(file_get_contents($root.'/'.$directory.'/'.$filename), true, 512, JSON_THROW_ON_ERROR);
    $runs[] = ['id' => $report['test_id'], 'profile' => 'operational-rehearsal', 'actual_result' => 'Executed isolated MySQL/Chrome rehearsal; see JSON',
        'status' => $report['status'], 'build_sha256' => $build['build_sha256'], 'base_commit' => $build['base_commit'],
        'executed_at_utc' => $report['finished_at_utc'], 'duration_seconds' => $report['duration_seconds'] ?? '', 'evidence_path' => $directory.'/'.$filename, 'bug_id' => ''];
}
[$header,$ledger] = resourceCsv($root.'/docs/testing/test-execution-results.csv');
$ledger = array_values(array_filter($ledger, fn ($row) => $row['build_sha256'] !== $build['build_sha256']));
resourceWrite($root.'/docs/testing/test-execution-results.csv', $header, [...$ledger, ...$runs]);
if (in_array('Failed', array_column($runs, 'status'), true)) { throw new RuntimeException('Failures recorded; UC promotion refused.'); }
$match = function (string $profile, string $fragment) use ($runs): array {
    $found = array_values(array_filter($runs, fn ($r) => $r['profile'] === $profile && str_contains($r['id'], $fragment)));
    if (! $found || array_diff(array_column($found, 'status'), ['Passed'])) { throw new RuntimeException('Successful execution required: '.$fragment); }
    return $found;
};
[$baselineHeader,$baseline] = resourceCsv($root.'/docs/testing/baseline-mapping.csv');
foreach ($baseline as &$row) {
    if (! $row['implementation'] || ! $row['profile']) { continue; }
    $found = $match($row['profile'], $row['implementation']);
    $row['status'] = 'Passed'; $row['evidence_path'] = implode(';', array_unique(array_column($found, 'evidence_path')));
} unset($row);
$aliases = [
    'UAT-UC10-MAIN' => [['browser','UAT-UC10:']],
    'UAT-UC10-ALT' => [['browser','UAT-UC10:']],
    'UAT-UC10-EXC' => [['mysql','test_aircraft_crud_normalization_revision_and_noneditable_usage']],
    'UAT-UC10-DENIED' => [['mysql','test_airport_scopes_filter_collections_and_protect_objects_and_configuration_history']],
    'UAT-UC11-MAIN' => [['mysql','test_usage_from_completed_facts_is_idempotent'],['mysql','test_threshold_alert_blocks_operations']],
    'UAT-UC11-ALT' => [['browser','UAT-UC11:']],
    'UAT-UC11-EXC' => [['browser','UAT-UC11:'],['mysql','FleetCommitRaceTest::test_configuration_and_usage_commit_with_two_independent_connections with data set #1']],
    'UAT-UC11-DENIED' => [['mysql','test_airport_scopes_filter_collections_and_protect_objects_and_configuration_history']],
    'UAT-UC15-MAIN' => [['browser','UAT-UC15:']],
    'UAT-UC15-ALT' => [['browser','UAT-UC15:']],
    'UAT-UC15-EXC' => [['mysql','test_profile_updates_validate_qualifications_identity_and_revision']],
    'UAT-UC15-DENIED' => [['mysql','test_backend_airport_and_flight_scopes_on_profiles_plans_and_actions']],
    'UAT-UC16-MAIN' => [['browser','UAT-UC16-17:']],
    'UAT-UC16-ALT' => [['mysql','test_suggestions_include_qualified_standby_and_warn_when_none_are_eligible']],
    'UAT-UC16-EXC' => [['mysql','test_suggestions_include_qualified_standby_and_warn_when_none_are_eligible'],['mysql','test_leave_blocks_assignment_and_cancellation_keeps_history'],['mysql','test_ineligible_replacement_rolls_back_original_assignment_and_events']],
    'UAT-UC16-DENIED' => [['mysql','test_backend_airport_and_flight_scopes_on_profiles_plans_and_actions']],
    'UAT-UC17-MAIN' => [['browser','UAT-UC16-17:'],['mysql','test_only_linked_member_or_scoped_operator_can_confirm']],
    'UAT-UC17-ALT' => [['browser','UAT-UC16-17:'],['mysql','test_approval_confirmation_and_replacement_have_actor_policy_and_immutable_events']],
    'UAT-UC17-EXC' => [['mysql','test_ineligible_replacement_rolls_back_original_assignment_and_events']],
    'UAT-UC17-DENIED' => [['mysql','test_only_linked_member_or_scoped_operator_can_confirm']],
];
[$caseHeader,$cases] = resourceCsv($root.'/docs/testing/test-cases.csv');
foreach ($cases as &$row) {
    $found = [];
    $base = array_values(array_filter($baseline, fn ($b) => $b['id'] === $row['id']));
    if ($base && $base[0]['status'] === 'Passed') { $found = $match($base[0]['profile'], $base[0]['implementation']); }
    if (isset($aliases[$row['id']])) { foreach ($aliases[$row['id']] as [$profile,$fragment]) { $found = [...$found, ...$match($profile,$fragment)]; } }
    if (! $found) { continue; }
    $row['status'] = 'Passed'; $row['implementation'] = implode(';', array_unique(array_column($found, 'id')));
    $row['evidence_path'] = implode(';', array_unique(array_column($found, 'evidence_path'))); $row['profile'] = implode('+', array_unique(array_column($found, 'profile')));
    $row['build_sha256'] = $build['build_sha256']; $row['tester'] = 'Automated PHPUnit/Playwright'; $row['executed_at_utc'] = $found[0]['executed_at_utc'];
    $row['actual_result'] = 'Executed source/baseline alias; listed executions provide evidence, aliases do not add test runs.';
    if (str_starts_with($row['id'], 'UAT-')) { $row['approval'] = 'Pending'; }
} unset($row);
resourceWrite($root.'/docs/testing/baseline-mapping.csv',$baselineHeader,$baseline);
resourceWrite($root.'/docs/testing/test-cases.csv',$caseHeader,$cases);
[$traceHeader,$trace] = resourceCsv($root.'/docs/traceability.csv');
foreach ($trace as &$row) {
    if (! in_array($row['uc_id'], ['UC-10','UC-11','UC-15','UC-16','UC-17'])) { continue; }
    $fleet = in_array($row['uc_id'], ['UC-10','UC-11']);
    $paths = $fleet
        ? ['app/Services/Core/AircraftService.php','app/Services/Core/FleetAccess.php','app/Services/Core/SeatLayout.php','app/Http/Controllers/Web/FleetController.php','tests/Feature/FleetManagementTest.php','tests/Feature/FleetCommitRaceTest.php','tests/Acceptance/fleet.spec.js']
        : ['app/Services/Core/CrewService.php','app/Services/Core/CrewAccess.php','app/Services/Core/CrewCalendar.php','app/Services/Core/CrewRulesService.php','app/Http/Controllers/Web/CrewWorkflowController.php','tests/Feature/CrewWorkflowTest.php','tests/Feature/FleetCommitRaceTest.php','tests/Acceptance/crew.spec.js'];
    $row['implementation_paths'] = implode(';',array_unique([...array_filter(explode(';',$row['implementation_paths'])),...$paths]));
    $row['status']='Implemented';
    $row['integration_test_ids']=implode(';',array_unique([...array_filter(explode(';',$row['integration_test_ids'])),
        $fleet?'Tests\\Feature\\FleetManagementTest':'Tests\\Feature\\CrewWorkflowTest','Tests\\Feature\\FleetCommitRaceTest']));
    $row['ui_route']=$fleet?'/admin/aircrafts;/admin/aircrafts/{tailNumber}':'/admin/crew;/admin/crew/{id};/admin/crew-plans;/admin/crew-plans/{flightId};/admin/workflows';
    $row['api_operation']=$fleet?'GET/POST /api/v1/core/aircrafts;GET/PUT/DELETE /api/v1/core/aircrafts/{tailNumber};GET/POST /api/v1/core/aircrafts/{tailNumber}/configurations;GET/POST /api/v1/core/aircrafts/{tailNumber}/usage'
        :'GET/POST /api/v1/core/crew-members;GET/PUT /api/v1/core/crew-members/{id};GET/POST /api/v1/core/crew-members/{id}/availability;POST /api/v1/core/crew-members/{id}/availability/{window}/cancel;GET /api/v1/core/flights/{flightId}/crew/suggestions;POST /api/v1/core/flights/{flightId}/crew/assign;POST /api/v1/core/flights/{id}/crew/approve;POST /api/v1/core/crew-assignments/{id}/confirm;POST /api/v1/core/crew-assignments/{id}/replace';
    $row['db_entities']=implode(';',array_unique([...explode(';',$row['db_entities']),...($fleet?['aircraft_configurations','aircraft_usage_alerts','aircraft_usage_receipts']:['crew_availability_windows','crew_rule_versions','crew_assignment_events'])]));
    $row['evidence_path']=$directory.'/mysql.xml;'.$directory.'/browser.xml';
    $row['open_issue']='Human UAT Pending; local numeric safety profiles require domain approval; full-release coverage/load/external integrations remain open. '.($fleet?'Imported aircraft without a verified physical map remain blocked at check-in.':'Rule profile local-crew-v1 is LOCAL_ONLY, not an approved operational standard.');
} unset($row);
resourceWrite($root.'/docs/traceability.csv',$traceHeader,$trace);
echo 'Recorded '.count($runs).' executions including skips/rehearsals. UC10/11/15/16/17 Implemented; human approval Pending.'.PHP_EOL;
