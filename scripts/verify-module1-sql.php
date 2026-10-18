<?php

// Integration check against a real MySQL/MariaDB server. Uses two NEW databases.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$connection = config('database.connections.mysql');
$suffix = gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$source = 'airline_m1_verify_'.$suffix;
$target = 'airline_m1_import_'.$suffix;
$server = new PDO(
    "mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
    $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
foreach ([$source, $target] as $database) {
    $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $source]);
DB::purge('mysql');
if (Artisan::call('migrate', ['--force' => true]) !== 0 || Artisan::call('db:seed', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
// Exercise escaping with Unicode, apostrophes, slashes, and newlines.
$original = DB::table('airports')->where('iata_code', 'HAN')->value('name');
$special = "Sân bay O'Hare \\ thử nghiệm\nDòng thứ hai";
DB::table('airports')->where('iata_code', 'HAN')->update(['name' => $special]);
$testSql = storage_path('app/module1-roundtrip.sql');
if (Artisan::call('export:module1-sql', ['--path' => $testSql]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
$import = new PDO(
    "mysql:host={$connection['host']};port={$connection['port']};dbname={$target};charset=utf8mb4",
    $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$import->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES'");
$import->exec(file_get_contents($testSql));
$tables = \App\Console\Commands\ExportModule1Sql::TABLES;
foreach ($tables as $table) {
    $key = DB::getSchemaBuilder()->getColumnListing($table)[0];
    $expected = DB::select("SELECT * FROM `{$table}` ORDER BY `{$key}`");
    $actual = $import->query("SELECT * FROM `{$table}` ORDER BY `{$key}`")->fetchAll(PDO::FETCH_OBJ);
    if (json_encode($expected) !== json_encode($actual)) {
        throw new RuntimeException("Round-trip data mismatch: {$table}");
    }
    echo "PASS {$table}: ".count($actual)." rows\n";
}
try {
    $import->exec("DELETE FROM airports WHERE iata_code='HAN'");
    throw new RuntimeException('Foreign key constraint was not enforced');
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') {
        throw $e;
    }
}
$archive = new PharData(preg_replace('/\.sql$/', '', $testSql).'.zip');
if ($archive['table1.sql']->getContent() !== file_get_contents($testSql)) {
    throw new RuntimeException('ZIP contents differ from SQL');
}
DB::table('airports')->where('iata_code', 'HAN')->update(['name' => $original]);
if (Artisan::call('export:module1-sql') !== 0) {
    throw new RuntimeException(Artisan::output());
}
echo "PASS UTF-8/special characters, foreign keys, ZIP contents, clean sample export\n";
echo "Verification databases retained: {$source}, {$target}\n";
