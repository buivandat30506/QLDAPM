<?php

// Import only completed executions for this checkpoint; retain all historical builds.
$root = dirname(__DIR__); $directory = 'docs/testing/evidence/2026-10-18-planning';
$build = json_decode(file_get_contents($root.'/'.$directory.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($build['files'] as $file => $expected) {
    if (! is_file($root.'/'.$file) || hash_file('sha256', $root.'/'.$file) !== $expected) { throw new RuntimeException('Source changed since snapshot: '.$file); }
}
function planningCsv(string $path): array {
    $f = fopen($path, 'r'); $header = fgetcsv($f, 0, ',', '"', ''); $rows = [];
    while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) { $rows[] = array_combine($header, $row); } fclose($f);
    return [$header, $rows];
}
function planningWrite(string $path, array $header, array $rows): void {
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
[$header,$ledger] = planningCsv($root.'/docs/testing/test-execution-results.csv');
$ledger = array_values(array_filter($ledger, fn ($row) => $row['build_sha256'] !== $build['build_sha256']));
planningWrite($root.'/docs/testing/test-execution-results.csv', $header, [...$ledger, ...$runs]);
if (in_array('Failed', array_column($runs, 'status'), true)) { throw new RuntimeException('Failures recorded; UC promotion refused.'); }
$match = function (string $profile, string $fragment) use ($runs): array {
    $found = array_values(array_filter($runs, fn ($r) => $r['profile'] === $profile && str_contains($r['id'], $fragment)));
    if (! $found || array_diff(array_column($found, 'status'), ['Passed'])) { throw new RuntimeException('Successful execution required: '.$fragment); }
    return $found;
};
[$baselineHeader,$baseline] = planningCsv($root.'/docs/testing/baseline-mapping.csv');
foreach ($baseline as &$row) {
    if (! $row['implementation'] || ! $row['profile']) { continue; }
    $found = $match($row['profile'], $row['implementation']);
    $row['status'] = 'Passed'; $row['evidence_path'] = implode(';', array_unique(array_column($found, 'evidence_path')));
} unset($row);
$aliases = [
    'UAT-UC04-MAIN' => [['browser','UAT-UC04:']], 'UAT-UC04-ALT' => [['browser','UAT-UC04:']],
    'UAT-UC04-EXC' => [['mysql','test_route_season_restrictions_validation_and_audit']],
    'UAT-UC04-DENIED' => [['mysql','test_airport_scoped_route_and_schedule_collections_objects_and_writes']],
    'UAT-UC05-MAIN' => [['browser','UAT-UC05-06:'],['mysql','test_full_schedule_workflow_records_actors_checks']],
    'UAT-UC05-ALT' => [['browser','UAT-UC05-06:'],['mysql','test_revision_keeps_published_schedule'],['mysql','test_changed_revision_lists_impacted_flights']],
    'UAT-UC05-EXC' => [['browser','UAT-UC06-EX:'],['mysql','test_missing_resources_and_multiple_conflicts'],['mysql','test_publish_rechecks_route_season']],
    'UAT-UC05-DENIED' => [['browser','UAT-UC06-EX:'],['mysql','test_airport_scoped_route_and_schedule']],
    'UAT-UC06-MAIN' => [['browser','UAT-UC05-06:'],['mysql','test_missing_resources_and_multiple_conflicts']],
    'UAT-UC06-ALT' => [['mysql','test_full_schedule_workflow_records_actors_checks']],
    'UAT-UC06-EXC' => [['browser','UAT-UC06-EX:']], 'UAT-UC06-DENIED' => [['browser','UAT-UC06-EX:']],
    'UAT-UC02-EXC' => [['mysql','test_all_source_resource_types_can_be_created_edited_and_suspended']],
    'UAT-UC18-EXC' => [['mysql','test_uc18_denied_ownership_validation_unique_document_and_required_consent']],
    'UAT-UC19-EXC' => [['mysql','test_uc19_flight_link_requires_valid_booking_and_airport_queue_is_scoped']],
];
[$caseHeader,$cases] = planningCsv($root.'/docs/testing/test-cases.csv');
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
planningWrite($root.'/docs/testing/baseline-mapping.csv',$baselineHeader,$baseline);
planningWrite($root.'/docs/testing/test-cases.csv',$caseHeader,$cases);
[$traceHeader,$trace] = planningCsv($root.'/docs/traceability.csv');
foreach ($trace as &$row) {
    if (isset(json_decode(file_get_contents($root.'/docs/requirements-docx-supplement.json'),true)['flow_overrides'][$row['uc_id']])) {
        $suffix = $row['uc_id'] === 'UC-08' ? 'ALT' : 'EXC';
        $row['uat_test_ids'] = implode(';',array_unique([...array_filter(explode(';',$row['uat_test_ids'])), 'UAT-'.str_replace('-','',$row['uc_id']).'-'.$suffix]));
    }
    if (! in_array($row['uc_id'], ['UC-04','UC-05','UC-06'])) { continue; }
    $row['status'] = 'Implemented';
    $row['implementation_paths'] = 'app/Services/Core/RouteService.php;app/Services/Core/ScheduleService.php;app/Services/Core/ScheduleConflictChecker.php;app/Services/Core/ScheduleOccurrence.php;app/Services/Core/PlanningLock.php;app/Http/Controllers/Web/PlanningController.php;tests/Feature/FlightPlanningTest.php;tests/Feature/PlanningCommitRaceTest.php';
    $row['ui_route'] = '/admin/routes;/admin/routes/{id};/admin/schedules;/admin/schedules/{id}';
    $row['unit_test_ids'] = $row['uc_id'] === 'UC-04' ? '' : 'Tests\\Unit\\ScheduleOccurrenceTest';
    $row['integration_test_ids'] = 'Tests\\Feature\\FlightPlanningTest;Tests\\Feature\\PlanningCommitRaceTest;Tests\\Feature\\OperationalResourceRaceTest';
    $row['db_entities'] = implode(';', array_unique([...explode(';',$row['db_entities']), 'flight_schedule_checks','domain_locks']));
    $row['api_operation'] = 'GET/POST /api/v1/core/routes;GET/PUT/DELETE /api/v1/core/routes/{id};GET/POST /api/v1/core/schedules;GET/PUT /api/v1/core/schedules/{id};POST /api/v1/core/schedules/{id}/check;POST /api/v1/core/schedules/{id}/{action};GET /api/v1/core/versions';
    $row['evidence_path'] = $directory.'/mysql.xml;'.$directory.'/browser.xml';
    $row['open_issue'] = 'UAT approval Pending; derived planning profile/366-day horizon and global mutex documented; full-release coverage/load/integrations remain open.';
} unset($row);
planningWrite($root.'/docs/traceability.csv',$traceHeader,$trace);
echo 'Recorded '.count($runs).' executions (includes skips/rehearsals); prior builds retained. UC04-06 Implemented, human approval Pending.'.PHP_EOL;
