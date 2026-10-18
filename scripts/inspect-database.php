<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
foreach (array_unique([(int) $connection['port'], 3306]) as $port) {
    try {
        $pdo = new PDO('mysql:host='.$connection['host'].';port='.$port.';charset=utf8mb4', $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $row = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product')->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['port' => $port] + $row, JSON_UNESCAPED_UNICODE).PHP_EOL;
    } catch (PDOException $exception) {
        echo json_encode(['port' => $port, 'status' => 'Unavailable', 'sqlstate' => $exception->getCode()]).PHP_EOL;
    }
}
