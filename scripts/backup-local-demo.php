<?php

// Read-only backup of the explicitly isolated demo database. Never print its environment or credentials.
$root = dirname(__DIR__);
$runtime = json_decode(file_get_contents($root.'/storage/app/private/demo-runtime.json'), true, 512, JSON_THROW_ON_ERROR);
if (($runtime['mode'] ?? '') !== 'local-demo' || ! preg_match('/^airline_demo_[a-zA-Z0-9_]+$/D', $runtime['database'] ?? '')) { throw new RuntimeException('Isolated local demo profile required.'); }
$env = array_merge(getenv(), $runtime['environment']);
if (($env['APP_ENV'] ?? '') !== 'local' || ($env['DB_CONNECTION'] ?? '') !== 'mysql') { throw new RuntimeException('Local MySQL only.'); }
$directory = $root.'/storage/app/private/backups'; if (! is_dir($directory)) { mkdir($directory, 0700, true); }
$file = $directory.'/before-maintenance-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
$env['MYSQL_PWD'] = $env['DB_PASSWORD'];
$exe = getenv('MYSQLDUMP_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe' : 'mysqldump');
$p = proc_open([$exe, '--host='.$env['DB_HOST'], '--port='.$env['DB_PORT'], '--user='.$env['DB_USERNAME'], '--protocol=TCP', '--default-character-set=utf8mb4',
    '--single-transaction', '--skip-add-drop-table', '--no-tablespaces', '--set-gtid-purged=OFF', '--column-statistics=0', '--hex-blob', $runtime['database']],
    [0 => ['pipe', 'r'], 1 => ['file', $file, 'w'], 2 => ['file', $file.'.stderr.log', 'w']], $pipes, $root, $env);
fclose($pipes[0]); $exit = proc_close($p); if ($exit !== 0) { throw new RuntimeException('Dump failed; inspect private stderr.'); }
$report = ['file' => 'storage/app/private/backups/'.basename($file), 'bytes' => filesize($file), 'sha256' => hash_file('sha256', $file),
    'created_at_utc' => gmdate('c'), 'profile' => 'isolated-local-demo', 'scope' => 'Database only; attachment storage and encryption key require separate backup.'];
file_put_contents($file.'.manifest.json', json_encode($report, JSON_PRETTY_PRINT)); echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
