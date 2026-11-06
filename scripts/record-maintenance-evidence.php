<?php

// Import only completed executions for this checkpoint; retain all historical builds.
$root = dirname(__DIR__); $directory = 'docs/testing/evidence/2026-10-18-maintenance';
$build = json_decode(file_get_contents($root.'/'.$directory.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$environment = getenv(); $environment['TEST_EVIDENCE_DIR'] = $root.'/'.$directory;
$process = proc_open([PHP_BINARY, $root.'/scripts/snapshot-build.php', '--verify'], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $root, $environment);
fclose($pipes[0]); if (proc_close($process) !== 0) { throw new RuntimeException('Complete source set verification failed.'); }
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
    'UAT-UC12-MAIN' => [['browser','UAT-UC12-14:']],
    'UAT-UC12-ALT' => [['browser','UAT-UC12-14:'],['mysql','test_plan_revisions_history_validation_and_overdue_deferral_are_enforced']],
    'UAT-UC12-EXC' => [['mysql','test_missing_usage_provenance_invalid_upload_and_resource_contention_fail_closed'],['mysql','test_flight_and_maintenance_window_conflicts_reject_without_partial_writes']],
    'UAT-UC12-DENIED' => [['mysql','test_airport_scope_protects_collections_orders_files_stock_and_admin_has_no_bypass']],
    'UAT-UC13-MAIN' => [['browser','UAT-UC12-14:'],['mysql','test_two_independent_orders_cannot_consume_the_same_material_stock']],
    'UAT-UC13-ALT' => [['browser','UAT-UC12-14:'],['mysql','test_incomplete_tasks_missing_evidence_or_signature_keep_work_in_progress']],
    'UAT-UC13-EXC' => [['mysql','test_material_shortage_persists_alert_receipt_is_idempotent_and_start_consumes_once'],['mysql','test_incomplete_tasks_missing_evidence_or_signature_keep_work_in_progress']],
    'UAT-UC13-DENIED' => [['mysql','test_airport_scope_protects_collections_orders_files_stock_and_admin_has_no_bypass']],
    'UAT-UC14-MAIN' => [['browser','UAT-UC12-14:'],['mysql','test_plan_date_hours_cycles_condition_and_due_alerts_block_planning_synchronously']],
    'UAT-UC14-ALT' => [['browser','UAT-UC12-14:'],['mysql','test_completion_approval_and_release_record_responsibility_without_resetting_unrelated_usage']],
    'UAT-UC14-EXC' => [['mysql','test_overdue_other_plan_and_tampered_evidence_block_technical_release'],['mysql','test_incomplete_tasks_missing_evidence_or_signature_keep_work_in_progress']],
    'UAT-UC14-DENIED' => [['mysql','test_airport_scope_protects_collections_orders_files_stock_and_admin_has_no_bypass']],
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
    if (! in_array($row['uc_id'], ['UC-12','UC-13','UC-14'])) { continue; }
    $paths = ['app/Services/Core/MaintenanceWorkflow.php','app/Services/Core/MaintenanceReadiness.php','app/Services/Core/MaintenanceAccess.php','app/Services/Core/MaintenanceService.php','app/Http/Controllers/Web/MaintenanceController.php','routes/maintenance.php','tests/Feature/MaintenanceWorkflowTest.php','tests/Feature/MaintenanceStockRaceTest.php','tests/Acceptance/maintenance.spec.js'];
    $row['implementation_paths'] = implode(';',array_unique([...array_filter(explode(';',$row['implementation_paths'])),...$paths]));
    $row['status']='Implemented';
    $row['integration_test_ids']=implode(';',array_unique([...array_filter(explode(';',$row['integration_test_ids'])),'Tests\\Feature\\MaintenanceWorkflowTest','Tests\\Feature\\MaintenanceStockRaceTest']));
    $row['ui_route']='/admin/maintenance/plans;/admin/maintenance/plans/{plan}';
    $row['api_operation']='GET/POST /api/v1/core/maintenance/plans;GET/PUT /api/v1/core/maintenance/plans/{plan};POST /api/v1/core/maintenance/plans/{plan}/orders;POST /api/v1/core/maintenance/orders/{order}/{action};POST /api/v1/core/maintenance/orders/{order}/adopt;POST /api/v1/core/maintenance/orders/{order}/evidence;GET /api/v1/core/maintenance/evidence/{evidence};POST /api/v1/core/maintenance/materials/receipts';
    $row['db_entities']=implode(';',array_unique([...explode(';',$row['db_entities']),'maintenance_plans','maintenance_order_tasks','maintenance_materials','maintenance_order_materials','maintenance_stock_moves','maintenance_evidence','maintenance_events','maintenance_alerts']));
    $row['evidence_path']=$directory.'/mysql.xml;'.$directory.'/browser.xml;'.$directory.'/maintenance-stock-race.json';
    $row['open_issue']='Human UAT Pending; LOCAL_ONLY display warning 24h and aggregate usage profile require engineering approval. Overdue release remains blocked; no override is silently inferred. External MRO is not connected. Global release coverage/load and remaining modules are still open.';
} unset($row);
resourceWrite($root.'/docs/traceability.csv',$traceHeader,$trace);
echo 'Recorded '.count($runs).' executions including skips/rehearsals. UC12/13/14 Implemented; human approval Pending.'.PHP_EOL;
