<?php

use App\Support\CorePruning;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$result = [];
foreach (['ops', 'mysql', 'finance'] as $node) {
    $db = DB::connection($node);
    $result[$node] = ['database' => $db->getDatabaseName(), 'username' => config("database.connections.$node.username"), 'tables' => (int) $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->n, 'pending_outbox' => $db->table('outbox')->whereNull('delivered_at')->count()];
    if ($result[$node]['database'] !== CorePruning::TARGET || $result[$node]['username'] !== 'airline') {
        throw new RuntimeException('Unexpected active application database/user');
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
