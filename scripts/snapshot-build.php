<?php

$root = dirname(__DIR__);
$files = [];
foreach (['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'scripts', 'tests'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'js', 'mjs', 'json', 'css', 'ps1', 'py', 'xml']) || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) { continue; }
        $files[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = hash_file('sha256', $file->getPathname());
    }
}
foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js', 'playwright.config.js', 'CODEX_MASTER_PROMPT_QL_HANG_KHONG.md', 'AGENTS.md', 'compose.yaml', 'docker/Dockerfile', '.dockerignore', '.github/workflows/tests.yml'] as $file) { $files[$file] = hash_file('sha256', $root.'/'.$file); }
ksort($files);
foreach (['docs/requirements-source.json', 'docs/source-register.json', 'docs/requirements-docx-supplement.json'] as $sourceFile) {
    $files[$sourceFile] = hash_file('sha256', $root.'/'.$sourceFile);
}
ksort($files);
$apiFiles = glob($root.'/docs/api/*.yaml');
foreach ($apiFiles as $apiFile) { $files['docs/api/'.basename($apiFile)] = hash_file('sha256', $apiFile); }
ksort($files);
$build = ['base_commit' => trim(shell_exec('git rev-parse HEAD')), 'worktree' => 'uncommitted; includes preserved user changes', 'snapshot_at_utc' => gmdate('c'), 'build_sha256' => hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES)), 'files' => $files];
$evidence = getenv('TEST_EVIDENCE_DIR') ?: $root.'/docs/testing/evidence/2026-10-18';
if (in_array('--verify', $argv, true)) {
    $expected = json_decode(file_get_contents($evidence.'/build-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($expected['build_sha256'] !== $build['build_sha256'] || $expected['files'] !== $files) { throw new RuntimeException('Source set/hash differs from the recorded build, including additions/deletions.'); }
    echo 'Complete source/API file set and SHA256 match recorded build.'.PHP_EOL; exit(0);
}
if (! is_dir($evidence)) { mkdir($evidence, 0777, true); }
file_put_contents($evidence.'/build-manifest.json', json_encode($build, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Build SHA256: '.$build['build_sha256'].PHP_EOL;
