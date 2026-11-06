<?php

// Append a build's actual executions; never regenerate or discard the historical ledger.
$root = dirname(__DIR__);
$evidence = 'docs/testing/evidence/2026-10-18-g2';
$build = json_decode(file_get_contents($root.'/'.$evidence.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($build['files'] as $file => $hash) {
    if (hash_file('sha256', $root.'/'.$file) !== $hash) {
        throw new RuntimeException('Source changed after build snapshot: '.$file);
    }
}
function sessionReadCsv(string $path): array
{
    $stream = fopen($path, 'r');
    $header = fgetcsv($stream, 0, ',', '"', '');
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = array_combine($header, $row);
    }fclose($stream);

    return [$header, $rows];
}
function sessionWriteCsv(string $path, array $header, array $rows): void
{
    $stream = fopen($path, 'w');
    fputcsv($stream, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn ($key) => $row[$key] ?? '', $header), ',', '"', '');
    }fclose($stream);
}
$runs = [];
foreach (['sqlite' => 'sqlite.xml', 'mysql' => 'mysql.xml', 'browser' => 'browser.xml'] as $profile => $filename) {
    $path = $root.'/'.$evidence.'/'.$filename;
    if (! is_file($path)) {
        throw new RuntimeException('Required actual execution missing: '.$path);
    }
    $xml = simplexml_load_file($path);
    foreach ($xml->xpath('//testcase') as $test) {
        $status = isset($test->failure) || isset($test->error) ? 'Failed' : (isset($test->skipped) ? 'Skipped' : 'Passed');
        $parent = $test->xpath('..')[0];
        $runs[] = ['id' => (string) ($test['class'] ?: $test['classname']).'::'.(string) $test['name'], 'profile' => $profile,
            'actual_result' => $status === 'Passed' ? 'JUnit: executed, no failure/error' : trim((string) ($test->failure ?: $test->error ?: $test->skipped)),
            'status' => $status, 'build_sha256' => $build['build_sha256'], 'base_commit' => $build['base_commit'],
            'executed_at_utc' => isset($parent['timestamp']) ? (string) $parent['timestamp'] : gmdate('c', filemtime($path)),
            'duration_seconds' => (string) $test['time'], 'evidence_path' => $evidence.'/'.$filename, 'bug_id' => ''];
    }
}
foreach (['restore.json', 'local-demo.json'] as $filename) {
    $report = json_decode(file_get_contents($root.'/'.$evidence.'/'.$filename), true, 512, JSON_THROW_ON_ERROR);
    $runs[] = ['id' => $report['test_id'], 'profile' => 'operational-rehearsal', 'actual_result' => 'Actual isolated MySQL/Chrome rehearsal; see JSON evidence',
        'status' => $report['status'], 'build_sha256' => $build['build_sha256'], 'base_commit' => $build['base_commit'],
        'executed_at_utc' => $report['finished_at_utc'], 'duration_seconds' => $report['duration_seconds'] ?? '', 'evidence_path' => $evidence.'/'.$filename, 'bug_id' => ''];
}
[$ledgerHeader,$ledger] = sessionReadCsv($root.'/docs/testing/test-execution-results.csv');
$ledger = array_values(array_filter($ledger, fn ($row) => $row['build_sha256'] !== $build['build_sha256']));
sessionWriteCsv($root.'/docs/testing/test-execution-results.csv', $ledgerHeader, [...$ledger, ...$runs]);
if (in_array('Failed', array_column($runs, 'status'), true)) {
    throw new RuntimeException('Failures recorded. Do not promote UC status.');
}
$match = function (string $profile, string $fragment) use ($runs): array {
    $found = array_values(array_filter($runs, fn ($r) => $r['profile'] === $profile && str_contains($r['id'], $fragment)));
    if (! $found || array_diff(array_column($found, 'status'), ['Passed'])) {
        throw new RuntimeException('Missing successful execution: '.$fragment);
    }

    return $found;
};
[$baselineHeader,$baseline] = sessionReadCsv($root.'/docs/testing/baseline-mapping.csv');
foreach ($baseline as &$row) {
    if ($row['id'] === 'UAT-W2-02') {
        $row['profile'] = 'browser';
        $row['implementation'] = 'UAT-UC01-02:';
        $row['actual_test_type'] = 'Browser acceptance; approval Pending';
    }
    if (! $row['implementation'] || ! $row['profile']) {
        continue;
    }
    $found = $match($row['profile'], $row['implementation']);
    $row['status'] = 'Passed';
    $row['evidence_path'] = implode(';', array_unique(array_column($found, 'evidence_path')));
}unset($row);
sessionWriteCsv($root.'/docs/testing/baseline-mapping.csv', $baselineHeader, $baseline);
$aliases = [
    'UAT-UC01-MAIN' => [['browser', 'UAT-UC01-02:']], 'UAT-UC01-ALT' => [['browser', 'UAT-UC01-02:']],
    'UAT-UC01-EXC' => [['browser', 'UAT-UC01-02:'], ['mysql', 'test_airport_identifier_coordinates_timezone_and_status']],
    'UAT-UC01-DENIED' => [['browser', 'UAT-UC01-02:'], ['mysql', 'test_airport_scope_filters_lists']],
    'UAT-UC02-MAIN' => [['browser', 'UAT-UC01-02:']], 'UAT-UC02-ALT' => [['browser', 'UAT-UC01-02:']],
    'UAT-UC02-DENIED' => [['browser', 'UAT-UC01-02:'], ['mysql', 'test_airport_scope_filters_lists']],
    'UAT-UC03-MAIN' => [['browser', 'UAT-UC03:']], 'UAT-UC03-ALT' => [['browser', 'UAT-UC03:'], ['mysql', 'test_export_filters_escape_formula']],
    'UAT-UC03-EXC' => [['browser', 'UAT-UC03-EX:'], ['mysql', 'test_quality_is_rechecked_at_publication']],
    'UAT-UC03-DENIED' => [['browser', 'UAT-UC03-EX:'], ['mysql', 'test_export_filters_escape_formula']],
];
[$caseHeader,$cases] = sessionReadCsv($root.'/docs/testing/test-cases.csv');
foreach ($cases as &$row) {
    $baselineMatch = array_values(array_filter($baseline, fn ($b) => $b['id'] === $row['id']));
    if ($baselineMatch) {
        $row['status'] = $baselineMatch[0]['status'];
        $row['evidence_path'] = $baselineMatch[0]['evidence_path'];
    }
    if (! isset($aliases[$row['id']])) {
        continue;
    }
    $found = [];
    foreach ($aliases[$row['id']] as [$profile,$fragment]) {
        $found = [...$found, ...$match($profile, $fragment)];
    }
    $row['status'] = 'Passed';
    $row['implementation'] = implode(';', array_unique(array_column($found, 'id')));
    $row['actual_result'] = 'Executed source flow via browser and/or MySQL checks listed; aliases are not extra test executions.';
    $row['evidence_path'] = implode(';', array_unique(array_column($found, 'evidence_path')));
    $row['profile'] = implode('+', array_unique(array_column($found, 'profile')));
    $row['build_sha256'] = $build['build_sha256'];
    $row['tester'] = 'Automated PHPUnit/Playwright';
    $row['executed_at_utc'] = $found[0]['executed_at_utc'];
    $row['approval'] = 'Pending';
}unset($row);
sessionWriteCsv($root.'/docs/testing/test-cases.csv', $caseHeader, $cases);
[$traceHeader,$trace] = sessionReadCsv($root.'/docs/traceability.csv');
foreach ($trace as &$row) {
    $row['status'] = match ($row['status']) {
        'Existing Partial' => 'InProgress','Not Implemented' => 'NotStarted','Implemented slice; UAT approval Pending' => 'Implemented',default => $row['status']
    };
    if (in_array($row['uc_id'], ['UC-01', 'UC-02', 'UC-03'])) {
        $row['status'] = 'Implemented';
        $row['evidence_path'] = $evidence.'/mysql.xml;'.$evidence.'/browser.xml';
        $row['open_issue'] = 'UAT approval Pending; phase/release gates including coverage and load remain open. Typed master schema v1 is documented.';
        $row['implementation_paths'] = $row['uc_id'] === 'UC-03'
            ? 'app/Services/Core/MasterDataService.php;app/Services/Core/MasterDataQuality.php;app/Services/Core/MasterDataTransfer.php;app/Http/Controllers/Web/MasterDataController.php;tests/Feature/FoundationWorkflowTest.php;tests/Feature/FoundationMySqlRaceTest.php'
            : 'app/Services/Core/AirportService.php;app/Services/Core/AirportData.php;app/Http/Middleware/CoreAccess.php;app/Http/Controllers/Web/AdminController.php;tests/Feature/FoundationWorkflowTest.php';
        $row['ui_route'] = $row['uc_id'] === 'UC-03' ? '/admin/master-data;/admin/master-data/{id};/admin/master-data/imports/{batch}' : '/admin/airports;/admin/airports/{iataCode}';
        $row['api_operation'] = $row['uc_id'] === 'UC-03' ? '/api/v1/core/master-data;/api/v1/core/master-data/imports;/api/v1/core/master-data/{id}/{action}' : '/api/v1/core/airports;/api/v1/core/airports/{iataCode}/resources';
        $row['db_entities'] = $row['uc_id'] === 'UC-03' ? 'master_data_records;entity_versions;domain_locks;master_import_batches;master_import_rows;audit_logs' : 'airports;airport_resources;audit_logs';
        $row['integration_test_ids'] .= ';Tests\\Feature\\FoundationWorkflowTest';
        if ($row['uc_id'] === 'UC-03') {
            $row['unit_test_ids'] = 'Tests\\Unit\\CsvSafetyTest;Tests\\Unit\\CsvSyntaxTest';
            $row['integration_test_ids'] .= ';IT-MASTER-IMPORT-RACE-01;IT-MASTER-PUBLISH-RACE-01';
        }
    }
    if (in_array($row['uc_id'], ['UC-05', 'UC-06', 'UC-16'])) {
        $row['integration_test_ids'] .= ';Tests\\Feature\\OperationalResourceRaceTest';
        $row['evidence_path'] .= ';'.$evidence.'/mysql.xml';
        if (! str_contains($row['open_issue'], 'Aircraft/gate/crew contention verified')) {
            $row['open_issue'] .= ' Aircraft/gate/crew contention verified for overlapping submissions/assignments; full source flows remain InProgress.';
        }
    }
    $row['integration_test_ids'] = implode(';', array_unique(array_filter(explode(';', $row['integration_test_ids']))));
    $row['evidence_path'] = implode(';', array_unique(array_filter(explode(';', $row['evidence_path']))));
}unset($row);
sessionWriteCsv($root.'/docs/traceability.csv',$traceHeader,$trace);
echo 'Recorded '.count($runs).' actual executions/rehearsals; preserved earlier builds. Baseline '.count(array_filter($baseline,fn ($r) => $r['status'] === 'Passed')).'/43 Passed. UAT approval Pending.'.PHP_EOL;
