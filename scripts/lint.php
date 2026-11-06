<?php

$root = dirname(__DIR__);
$count = 0;
$failed = 0;
foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'scripts', 'tests'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) { continue; }
        $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { echo $output; $failed++; }
        $count++;
    }
}
echo "PHP syntax: $count files checked, $failed failed.".PHP_EOL;
exit($failed ? 1 : 0);
