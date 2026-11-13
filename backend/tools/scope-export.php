<?php

use App\Support\CorePruning;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$dir = $argv[1] ?? throw new RuntimeException('Private output directory required');
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
foreach (['ops' => 'db1', 'mysql' => 'db2', 'finance' => 'db3'] as $node => $service) {
    $db = DB::connection($node);
    if (! app()->environment('local', 'testing') || ! in_array($db->getDatabaseName(), ['airline', CorePruning::TARGET], true)) {
        throw new RuntimeException('Local original or dedicated core copy only');
    }
    $tables = array_map(fn ($r) => $r->TABLE_NAME, $db->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"));
    $edges = $db->select('SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
    $sorted = [];
    while ($tables) {
        $progress = false;
        foreach ($tables as $index => $table) {
            foreach ($edges as $edge) {
                if ($edge->TABLE_NAME === $table && $edge->REFERENCED_TABLE_NAME !== $table && ! in_array($edge->REFERENCED_TABLE_NAME, $sorted, true)) {
                    continue 2;
                }
            }
            $sorted[] = $table;
            unset($tables[$index]);
            $progress = true;
        }
        if (! $progress) {
            throw new RuntimeException('Cyclic table dependencies require review');
        }
    }
    $out = fopen($dir.'/'.$service.'.sql', 'xb');
    fwrite($out, "SET NAMES utf8mb4;\n");
    foreach ($sorted as $table) {
        $ddl = array_values((array) $db->selectOne('SHOW CREATE TABLE `'.$table.'`'))[1];
        fwrite($out, $ddl.";\n");
        $columns = $db->getSchemaBuilder()->getColumnListing($table);
        $prefix = 'INSERT INTO `'.$table.'` (`'.implode('`,`', $columns).'`) VALUES ';
        $batch = [];
        foreach ($db->table($table)->cursor() as $row) {
            $row = (array) $row;
            $values = array_map(fn ($c) => $row[$c] === null ? 'NULL' : $db->getPdo()->quote((string) $row[$c]), $columns);
            $batch[] = '('.implode(',', $values).')';
            if (count($batch) >= 100) {
                fwrite($out, $prefix.implode(',', $batch).";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($out, $prefix.implode(',', $batch).";\n");
        }
    }
    fclose($out);
    chmod($dir.'/'.$service.'.sql', 0600);
    echo $service.' exported '.count($sorted)." tables\n";
}
