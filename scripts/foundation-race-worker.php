<?php

use App\Models\User;
use App\Services\Core\MasterDataService;
use App\Services\Core\MasterDataTransfer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', config('database.connections.mysql.database'))) {
    throw new RuntimeException('Race workers require an isolated MySQL test database.');
}
Auth::login(User::findOrFail($argv[3]));
echo json_encode(['ready' => true, 'connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
    'database' => DB::connection()->getDatabaseName()]).PHP_EOL;
flush();
try {
    if ($argv[1] === 'import') {
        $record = app(MasterDataTransfer::class)->commit($argv[2]);
    } elseif ($argv[1] === 'publish') {
        $record = app(MasterDataService::class)->transition((int) $argv[2], 'publish');
    } else {
        throw new RuntimeException('Unknown race mode.');
    }
    echo json_encode(['result' => 'committed', 'id' => $record->id]).PHP_EOL;
} catch (ValidationException $e) {
    echo json_encode(['result' => 'rejected', 'fields' => array_keys($e->errors())]).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'type' => get_class($e), 'file' => basename($e->getFile()), 'line' => $e->getLine()]).PHP_EOL;
    exit(1);
}
