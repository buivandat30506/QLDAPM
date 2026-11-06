<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('app.key')) {
    echo "Existing APP_KEY preserved.\n";
    exit(0);
}
if (! $app->environment(['local', 'testing'])) {
    throw new RuntimeException('Missing APP_KEY outside local/testing; restore the original key.');
}
$connection = DB::connection();
if ($connection->getSchemaBuilder()->hasTable('migrations') && $connection->table('migrations')->exists()) {
    throw new RuntimeException('Database already has migrations but APP_KEY is missing. Restore the original key; automatic rotation refused.');
}
exit($kernel->call('key:generate') === 0 ? 0 : 1);
