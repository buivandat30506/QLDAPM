<?php

use App\Models\AssistanceRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Rehearsal only: source and NEW target must both belong to the isolated test namespace.
$root = dirname(__DIR__);
$fixture = json_decode(file_get_contents($root.'/storage/app/browser-database.json'), true, 512, JSON_THROW_ON_ERROR);
$source = $fixture['database'];
if (! preg_match('/^airline_test_browser_[a-zA-Z0-9_]+$/D', $source)) {
    throw new RuntimeException('Only prepared synthetic browser fixtures may be backed up here.');
}
$host = $fixture['host'];
$port = (string) $fixture['port'];
$user = $fixture['username'];
$password = getenv('TEST_MYSQL_PASSWORD') ?: '';
$connect = fn (string $db = '') => new PDO("mysql:host=$host;port=$port;".($db !== '' ? "dbname=$db;" : '').'charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = $connect();
$engine = $server->query('SELECT VERSION() AS version, @@version_comment AS product')->fetch(PDO::FETCH_ASSOC);
if (stripos($engine['version'].' '.$engine['product'], 'MariaDB') !== false) {
    throw new RuntimeException('This rehearsal requires MySQL.');
}
$target = 'airline_test_restore_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$folder = $root.'/storage/app/private/restore-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($folder, 0700, true);
$started = microtime(true);
$dumpBinary = getenv('MYSQLDUMP_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe' : 'mysqldump');
$mysqlBinary = getenv('MYSQL_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysql.exe' : 'mysql');
$environment = getenv();
$environment['MYSQL_PWD'] = $password;
$clientArgs = ['--host='.$host, '--port='.$port, '--user='.$user, '--protocol=TCP', '--default-character-set=utf8mb4'];
$run = function (array $command, array $descriptors) use ($root, $environment): void {
    $process = proc_open($command, $descriptors, $pipes, $root, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start MySQL client.');
    }
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('MySQL client failed; inspect private restore log. Exit '.$exit);
    }
};
$run([$dumpBinary, ...$clientArgs, '--single-transaction', '--skip-add-drop-table', '--no-tablespaces', '--set-gtid-purged=OFF', '--column-statistics=0', '--hex-blob', $source],
    [0 => ['pipe', 'r'], 1 => ['file', $folder.'/database.sql', 'w'], 2 => ['file', $folder.'/dump.stderr.log', 'w']]);
$fixtureBytes = random_bytes(256);
file_put_contents($folder.'/upload-fixture.bin', $fixtureBytes);
$attachmentRows = $connect($source)->query('SELECT id, order_id, path, sha256, bytes FROM maintenance_evidence ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$attachmentFiles = [];
foreach ($attachmentRows as $row) {
    if (! preg_match('#^maintenance/[a-zA-Z0-9]+\.(pdf|png|jpg|jpeg)$#D', $row['path'])) { throw new RuntimeException('Unsafe maintenance attachment path.'); }
    $path = $root.'/storage/app/private/'.$row['path'];
    if (! is_file($path) || hash_file('sha256', $path) !== $row['sha256'] || filesize($path) !== (int) $row['bytes']) { throw new RuntimeException('Source maintenance attachment missing or modified.'); }
    $attachmentFiles['uploads/'.$row['path']] = $row['sha256'];
}
$manifest = ['source_database' => $source, 'engine' => $engine, 'created_at_utc' => gmdate('c'),
    'files' => ['database.sql' => hash_file('sha256', $folder.'/database.sql'), 'uploads/upload-fixture.bin' => hash('sha256', $fixtureBytes), ...$attachmentFiles],
    'maintenance_evidence' => $attachmentRows,
    'encryption_key' => 'Stored separately; reuse the source application key. No key is present in this backup.'];
file_put_contents($folder.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
$archive = new PharData($folder.'/backup.tar');
$archive->addFile($folder.'/database.sql', 'database.sql');
$archive->addFile($folder.'/upload-fixture.bin', 'uploads/upload-fixture.bin');
$archive->addFile($folder.'/manifest.json', 'manifest.json');
foreach ($attachmentFiles as $name => $hash) { $archive->addFile($root.'/storage/app/private/'.substr($name, 8), $name); }
unset($archive);
$restored = $folder.'/extracted';
mkdir($restored, 0700);
$archive = new PharData($folder.'/backup.tar');
if (! $archive->extractTo($restored, ['database.sql', 'uploads/upload-fixture.bin', 'manifest.json', ...array_keys($attachmentFiles)], false)) {
    throw new RuntimeException('Archive extraction failed.');
}
unset($archive);
foreach ($manifest['files'] as $name => $hash) {
    if (hash_file('sha256', $restored.'/'.$name) !== $hash) {
        throw new RuntimeException('Backup checksum mismatch.');
    }
}
$server->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$run([$mysqlBinary, ...$clientArgs, '--database='.$target], [0 => ['file', $restored.'/database.sql', 'r'], 1 => ['file', $folder.'/restore.stdout.log', 'w'], 2 => ['file', $folder.'/restore.stderr.log', 'w']]);
$sourcePdo = $connect($source);
$targetPdo = $connect($target);
$tables = [];
foreach ($sourcePdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
        throw new RuntimeException('Unexpected table identifier.');
    }
    $fingerprint = function (PDO $pdo) use ($table): array {
        $hashes = [];
        $statement = $pdo->query("SELECT * FROM `$table`");
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes, SORT_STRING);

        return ['count' => count($hashes), 'sha256' => hash('sha256', implode('', $hashes))];
    };
    $before = $fingerprint($sourcePdo);
    $after = $fingerprint($targetPdo);
    if ($before !== $after) {
        throw new RuntimeException('Restored table differs: '.$table);
    }
    $tables[$table] = $after;
}
$foreignKeys = $targetPdo->prepare('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
$foreignKeys->execute([$target]);
$groups = [];
foreach ($foreignKeys->fetchAll(PDO::FETCH_ASSOC) as $fk) {
    $groups[$fk['TABLE_NAME'].'/'.$fk['CONSTRAINT_NAME']][] = $fk;
}
foreach ($groups as $columns) {
    $table = $columns[0]['TABLE_NAME'];
    $reference = $columns[0]['REFERENCED_TABLE_NAME'];
    $join = [];
    $nonNull = [];
    foreach ($columns as $fk) {
        $join[] = 's.`'.$fk['COLUMN_NAME'].'`=t.`'.$fk['REFERENCED_COLUMN_NAME'].'`';
        $nonNull[] = 's.`'.$fk['COLUMN_NAME'].'` IS NOT NULL';
    }
    $missing = $targetPdo->query("SELECT COUNT(*) FROM `$table` s LEFT JOIN `$reference` t ON ".implode(' AND ', $join).' WHERE '.implode(' AND ', $nonNull).' AND t.`'.$columns[0]['REFERENCED_COLUMN_NAME'].'` IS NULL')->fetchColumn();
    if ((int) $missing !== 0) {
        throw new RuntimeException('Orphaned relationship after restore.');
    }
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $source, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'APP_CONFIG_CACHE' => $root.'/storage/app/no-restore-config.php'] as $key => $value) {
    putenv($key.'='.$value);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$original = AssistanceRequest::query()->firstOrFail();
$originalDetails = $original->details;
config(['database.connections.mysql.database' => $target]);
DB::purge('mysql');
$restoredDetails = AssistanceRequest::findOrFail($original->id)->details;
if ($originalDetails !== $restoredDetails) {
    throw new RuntimeException('Restored encrypted content cannot be decrypted with the original key.');
}
$evidence = getenv('TEST_EVIDENCE_DIR') ?: $root.'/docs/testing/evidence/2026-10-18-g2';
$report = ['test_id' => 'IT-RESTORE-01', 'status' => 'Passed', 'source_database' => $source, 'target_database' => $target, 'engine' => $engine,
    'started_at_utc' => gmdate('c', (int) $started), 'finished_at_utc' => gmdate('c'),
    'duration_seconds' => round(microtime(true) - $started, 3), 'tables' => $tables, 'foreign_key_relationships_checked' => count($groups),
    'orphaned_relationships' => 0, 'encrypted_assistance_decrypted' => true, 'upload_fixture_sha256' => $manifest['files']['uploads/upload-fixture.bin'],
    'archive_sha256' => hash_file('sha256', $folder.'/backup.tar'), 'private_archive' => str_replace($root.'/', '', $folder).'/backup.tar',
    'maintenance_attachments_restored' => count($attachmentRows), 'maintenance_attachment_hashes_match_database' => true,
    'limitations' => ['Synthetic fixture only; includes actual maintenance attachments created by browser workflow.', 'RPO/RTO and production restore were not measured.']];
file_put_contents($evidence.'/restore.json',json_encode($report,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Restore Passed: '.count($tables).' table fingerprints; '.count($groups).' FK relationships; encrypted assistance and upload fixture verified. Target retained: '.$target.PHP_EOL;
