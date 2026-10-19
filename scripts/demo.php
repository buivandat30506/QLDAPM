<?php

$root = dirname(__DIR__);
$profile = $root.'/storage/app/private/demo-runtime.json';
if (! is_file($profile)) {
    throw new RuntimeException('Run php scripts/setup-local-demo.php first.');
}
$runtime = json_decode(file_get_contents($profile), true, 512, JSON_THROW_ON_ERROR);
if (($runtime['mode'] ?? '') !== 'local-demo' || ! preg_match('/^airline_demo_[a-zA-Z0-9_]+$/D', $runtime['database'] ?? '')) {
    throw new RuntimeException('Invalid local demo profile.');
}
$command = $argv[1] ?? 'about';
if (! in_array($command, ['serve', 'about', 'migrate', 'migrate:status', 'maintenance:alerts', 'queue:work', 'queue:failed', 'queue:retry', 'schedule:work', 'schedule:run', 'schedule:list', 'app:health', 'access:bootstrap', 'access:scope'])) {
    throw new RuntimeException('Command is not enabled by this local demo launcher.');
}
$environment = array_merge(getenv(), $runtime['environment']);
$process = proc_open([PHP_BINARY, $root.'/artisan', ...array_slice($argv, 1) ?: ['about']], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root, $environment);
exit(proc_close($process));
