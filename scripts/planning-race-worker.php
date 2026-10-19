<?php

use App\Services\Core\FlightManagementService;
use App\Services\Core\MaintenanceService;
use App\Services\Core\ScheduleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', config('database.connections.mysql.database'))) { throw new RuntimeException('Isolated MySQL testing only.'); }
echo json_encode(['ready' => true, 'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
    'database' => DB::connection()->getDatabaseName()]).PHP_EOL;
flush();
try {
    $data = json_decode($argv[3] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    $result = match ($argv[1]) {
        'publish', 'submit' => app(ScheduleService::class)->transition($argv[2], $argv[1]),
        'flight' => app(FlightManagementService::class)->createOperationalFlight($data),
        'maintenance' => app(MaintenanceService::class)->plan($data),
        'maintenance-start' => app(\App\Services\Core\MaintenanceWorkflow::class)->action((int) $argv[2], 'start', $data),
        'maintenance-plan' => app(\App\Services\Core\MaintenanceWorkflow::class)->savePlan($data),
        'configure' => app(\App\Services\Core\AircraftService::class)->configure($argv[2], $data),
        'usage' => app(\App\Services\Core\AircraftService::class)->receiveUsage($argv[2], $data),
        'crew-approve' => (object) ['id'=> $argv[2], 'assignments'=>app(\App\Services\Core\CrewService::class)->approvePlan($argv[2])],
        'crew-leave' => app(\App\Services\Core\CrewCalendar::class)->add($argv[2], $data),
        'crew-profile' => app(\App\Services\Core\CrewService::class)->updateCrewMember($argv[2], $data),
        default => throw new RuntimeException('Unknown operation.'),
    };
    echo json_encode(['result' => 'committed', 'operation' => $argv[1], 'id' => $result->id]).PHP_EOL;
} catch (ValidationException $e) {
    echo json_encode(['result' => 'rejected', 'operation' => $argv[1], 'fields' => array_keys($e->errors())]).PHP_EOL;
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() !== 409) { throw $e; }
    echo json_encode(['result' => 'rejected', 'operation' => $argv[1], 'status' => 409]).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'type' => get_class($e), 'line' => $e->getLine()]).PHP_EOL;
    exit(1);
}
