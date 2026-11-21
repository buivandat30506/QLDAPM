<?php

use App\Support\CorePruning;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
CorePruning::guard();
foreach (['ops', 'mysql', 'finance'] as $node) {
    $db = DB::connection($node);
    $tables = $db->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    foreach ($tables as $t) {
        foreach ($db->select('OPTIMIZE TABLE `'.$t->TABLE_NAME.'`') as $result) {
            if ($result->Msg_type === 'error') {
                throw new RuntimeException($result->Msg_text);
            }
        }
    }
    echo $node.' compacted '.count($tables)." retained tables; FK checks stayed enabled.\n";
}
