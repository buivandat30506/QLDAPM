<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', config('database.connections.mysql.database'))) {
    throw new RuntimeException('Race workers only run against isolated MySQL test databases.');
}
$connection = Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id');
echo json_encode(['ready' => true, 'connection_id' => $connection->id, 'database' => Illuminate\Support\Facades\DB::connection()->getDatabaseName()]).PHP_EOL;
flush();
// Parent already holds this flight row, so the booking transaction is the barrier.
try {
    $booking = app(App\Services\Passenger\BookingService::class)->createBooking([
        'customer_user_id' => $argv[2], 'flight_id' => $argv[1], 'passengers' => [[
            'id_card_passport' => 'SYNTHETIC-RACE-'.$argv[3], 'full_name' => 'Race fixture '.$argv[3], 'dob' => '1995-01-01', 'gender' => 'OTHER',
        ]],
    ]);
    echo json_encode(['result' => 'created', 'booking_id' => $booking->id]).PHP_EOL;
} catch (Illuminate\Validation\ValidationException $exception) {
    echo json_encode(['result' => 'rejected', 'fields' => array_keys($exception->errors())]).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'type' => get_class($exception), 'file' => basename($exception->getFile()), 'line' => $exception->getLine()]).PHP_EOL;
    exit(1);
}
