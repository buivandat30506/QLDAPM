<?php

// Separate random DB; never edit .env or use an existing database as destructive target.
$root = dirname(__DIR__);
$port = getenv('TEST_MYSQL_PORT') ?: '3318';
$host = getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
$username = getenv('TEST_MYSQL_USER') ?: 'root';
$password = getenv('TEST_MYSQL_PASSWORD') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product')->fetch(PDO::FETCH_ASSOC);
if (stripos($engine['version'].' '.$engine['product'], 'MariaDB') !== false) { throw new RuntimeException('MySQL profile requires MySQL, not MariaDB.'); }
$database = 'airline_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/^airline_test_[a-zA-Z0-9_]+$/D', $database)) { throw new RuntimeException('Unsafe database name.'); }
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$evidence = getenv('TEST_EVIDENCE_DIR') ?: $root.'/docs/testing/evidence/2026-10-18';
$filterArguments = array_values(array_filter(array_slice($argv, 1), fn ($argument) => str_starts_with($argument, '--filter=')));
$stem = $filterArguments ? 'mysql-focused' : 'mysql';
@mkdir($evidence, 0777, true);
file_put_contents($evidence.'/'.$stem.'-environment.json', json_encode(['engine' => $engine, 'database' => $database, 'host' => $host, 'port' => $port, 'php' => PHP_VERSION, 'started_at_utc' => gmdate('c'), 'profile' => 'isolated-synthetic-test'], JSON_PRETTY_PRINT));
$environment = getenv();
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'APP_CONFIG_CACHE' => $root.'/storage/app/mysql-testing/no-config-cache.php'] as $key => $value) { $environment[$key] = $value; }
$process = proc_open([PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit', '--colors=never', '--log-junit='.$evidence.'/'.$stem.'.xml', ...$filterArguments], [0 => ['pipe', 'r'], 1 => ['file', $evidence.'/'.$stem.'.txt', 'w'], 2 => ['file', $evidence.'/'.$stem.'-stderr.txt', 'w']], $pipes, $root, $environment);
fclose($pipes[0]);
$exit = proc_close($process);
echo file_get_contents($evidence.'/'.$stem.'.txt');
echo file_get_contents($evidence.'/'.$stem.'-stderr.txt');
echo 'Engine: '.$engine['version'].' '.$engine['product'].PHP_EOL.'Test database retained: '.$database.PHP_EOL.'Exit: '.$exit.PHP_EOL;
exit($exit);
