<?php

use App\Support\ScopeData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection('ops');
if (! app()->environment('local', 'testing') || config('database.connections.ops.host') !== 'db1' || ! in_array($db->getDatabaseName(), ['airline', 'airline_scope_copy'], true)) {
    throw new RuntimeException('Local scope database only');
}
$mode = $argv[1] ?? '';
$plan = json_decode(file_get_contents(storage_path('app/private/scope-plan.json')), true, 512, JSON_THROW_ON_ERROR);
$topology = (array) $db->selectOne('SELECT @@server_uuid AS uuid, @@hostname AS host');
if ($topology !== $plan['topology']) {
    throw new RuntimeException('Topology changed');
}
$entries = array_column($plan['rows'], 'sha256', 'id');
$file = storage_path('app/private/scope-deleted-rows.json');
if ($mode === 'archive') {
    if (is_file($file)) {
        throw new RuntimeException('Archive already exists; refusing overwrite');
    }
    $rows = [];
    foreach ($entries as $id => $hash) {
        $row = $db->table('telemetry')->where('id', $id)->first();
        if (! $row || ScopeData::fingerprint($row) !== $hash) {
            throw new RuntimeException('Candidate changed');
        }
        $rows[] = (array) $row;
    }
    file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($file, 0600);
    echo 'Archived exact telemetry rows: '.count($rows)."\n";
} elseif ($mode === 'restore') {
    $rows = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (count($rows) !== count($entries)) {
        throw new RuntimeException('Archive size mismatch');
    }
    $count = $db->transaction(function () use ($db, $rows, $entries) {
        $count = 0;
        foreach ($rows as $row) {
            $hash = $entries[$row['id']] ?? throw new RuntimeException('Unplanned ID');
            if (ScopeData::fingerprint($row) !== $hash) {
                throw new RuntimeException('Archive fingerprint mismatch');
            }
            $existing = $db->table('telemetry')->where('id', $row['id'])->lockForUpdate()->first();
            if ($existing) {
                if (ScopeData::fingerprint($existing) !== $hash) {
                    throw new RuntimeException('Existing row differs');
                }

                continue;
            }
            $db->table('telemetry')->insert($row);
            $count++;
        }

        return $count;
    });
    echo 'Restored exact telemetry rows: '.$count."\n";
} else {
    throw new RuntimeException('Use archive or restore');
}
