<?php

$root = dirname(__DIR__);
$source = json_decode(file_get_contents($root.'/docs/requirements-source.json'), true, 512, JSON_THROW_ON_ERROR);
$supplement = json_decode(file_get_contents($root.'/docs/requirements-docx-supplement.json'), true, 512, JSON_THROW_ON_ERROR);
if ($supplement['master_sha256'] !== $source['source_sha256'] || $supplement['use_case_count'] !== 48) { throw new RuntimeException('Source supplement does not match the master baseline.'); }
foreach (json_decode(file_get_contents($root.'/docs/source-register.json'), true, 512, JSON_THROW_ON_ERROR) as $document) {
    if (hash_file('sha256', $root.'/'.$document['source']) !== $document['sha256']) { throw new RuntimeException('DOCX source changed: '.$document['source']); }
}
if ($source['source_sha256'] !== hash_file('sha256', $root.'/CODEX_MASTER_PROMPT_QL_HANG_KHONG.md')) { throw new RuntimeException('Master prompt changed; re-extract and review source mapping.'); }
$file = fopen($root.'/docs/traceability.csv', 'r');
$header = fgetcsv($file); $trace = [];
while (($row = fgetcsv($file)) !== false) { $trace[$row[1]] = array_combine($header, $row); }
fclose($file);
if (count($trace) !== 48 || count(array_unique(array_column($trace, 'module_id'))) !== 16) { throw new RuntimeException('Trace must contain 48 UC / 16 modules.'); }
$file = fopen($root.'/docs/testing/test-cases.csv', 'r'); $header = fgetcsv($file); $cases = [];
while (($row = fgetcsv($file)) !== false) { $case = array_combine($header, $row); $cases[$case['id']] = $case; }
fclose($file);
foreach ($source['use_cases'] as $uc) {
    $uc['attributes'] = array_replace($uc['attributes'], $supplement['flow_overrides'][$uc['uc_id']] ?? []);
    if ($trace[$uc['uc_id']]['source_priority'] !== $uc['attributes']['Priority']) { throw new RuntimeException('Priority mismatch: '.$uc['uc_id']); }
    foreach (['MAIN' => 'Basic Flow', 'ALT' => 'Alternative Flow', 'EXC' => 'Exception Flow', 'DENIED' => null] as $suffix => $field) {
        if ($field && empty($uc['attributes'][$field])) { continue; }
        $id = 'UAT-'.str_replace('-', '', $uc['uc_id']).'-'.$suffix;
        if (! isset($cases[$id])) { throw new RuntimeException('Missing source case: '.$id); }
        if ($field && $cases[$id]['input_steps'] !== $uc['attributes'][$field]) { throw new RuntimeException('Source flow changed: '.$id); }
    }
    foreach ($uc['attributes'] as $value) { if (str_contains($value, '**Business Rule**')) { throw new RuntimeException('Malformed empty source field: '.$uc['uc_id']); } }
    foreach (explode(';', $trace[$uc['uc_id']]['implementation_paths']) as $path) { if ($path && ! file_exists($root.'/'.$path)) { throw new RuntimeException('Missing implementation path: '.$path); } }
}
echo 'Traceability verified: 48 UC, 16 modules, exact priorities and main/alternative/exception source flows; planned tests are not execution evidence.'.PHP_EOL;
