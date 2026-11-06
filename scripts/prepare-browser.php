<?php

$root = dirname(__DIR__);
$host = getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('TEST_MYSQL_PORT') ?: '3318';
$username = getenv('TEST_MYSQL_USER') ?: 'root';
$password = getenv('TEST_MYSQL_PASSWORD') ?: '';
$database = 'airline_test_browser_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product')->fetch(PDO::FETCH_ASSOC);
if (stripos($engine['version'], 'MariaDB') !== false) { throw new RuntimeException('Browser profile requires MySQL.'); }
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'APP_CONFIG_CACHE' => $root.'/storage/app/no-browser-config.php'] as $key => $value) { putenv($key.'='.$value); }
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if ($kernel->call('migrate', ['--force' => true]) !== 0 || $kernel->call('db:seed', ['--force' => true]) !== 0) { throw new RuntimeException('Browser fixture setup failed.'); }
file_put_contents($root.'/storage/app/browser-database.json', json_encode(compact('database', 'host', 'port', 'username', 'engine'), JSON_PRETTY_PRINT));
echo 'Browser fixture on '.$engine['version'].' database '.$database.PHP_EOL;
