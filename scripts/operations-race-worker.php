<?php

use App\Services\Core\CrewService;
use App\Services\Core\ScheduleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', config('database.connections.mysql.database'))) {
    throw new RuntimeException('Isolated MySQL testing only.');
}
echo json_encode(['ready' => true, 'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
    'database' => DB::connection()->getDatabaseName()]).PHP_EOL;
flush();
try {
    if ($argv[1] === 'schedule') {
        $result = app(ScheduleService::class)->transition($argv[2], 'submit');
    } elseif ($argv[1] === 'crew') {
        $result = app(CrewService::class)->assignCrewToFlight($argv[2], ['crew_id' => $argv[3], 'assigned_position' => 'CAPTAIN']);
    } else {
        throw new RuntimeException('Unknown operation.');
    }
    echo json_encode(['result' => 'committed', 'id' => $result->id]).PHP_EOL;
} catch (ValidationException $e) {
    echo json_encode(['result' => 'rejected', 'fields' => array_keys($e->errors())]).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'type' => get_class($e), 'line' => $e->getLine()]).PHP_EOL;
    exit(1);
}
