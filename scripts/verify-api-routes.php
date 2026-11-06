<?php

require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__); $app = require $root.'/bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes = $app->make('router')->getRoutes(); $normalize = fn ($p) => preg_replace('/\{[^}]+\}/', '{}', trim($p, '/')); $checked = [];
foreach (['foundation.openapi.yaml', 'flight-planning.openapi.yaml', 'flight-operations.openapi.yaml', 'fleet-crew.openapi.yaml', 'maintenance.openapi.yaml'] as $file) {
    $api = \Symfony\Component\Yaml\Yaml::parseFile($root.'/docs/api/'.$file); $base = parse_url($api['servers'][0]['url'], PHP_URL_PATH);
    foreach ($api['paths'] as $path => $operations) {
        foreach ($operations as $verb => $operation) {
            if (! in_array($verb, ['get', 'post', 'put', 'delete', 'patch'])) { continue; }
            $matches = [];
            foreach ($routes as $route) {
                if (in_array(strtoupper($verb), $route->methods()) && $normalize($route->uri()) === $normalize(rtrim($base, '/').$path)) { $matches[] = $route; }
            }
            if (! $matches) { throw new RuntimeException('Documented operation has no Laravel route: '.$verb.' '.$path); }
            $checked[] = ['operation_id' => $operation['operationId'], 'method' => strtoupper($verb), 'document' => $file, 'uri' => $matches[0]->uri()];
        }
    }
}
$dir = getenv('TEST_EVIDENCE_DIR');
if ($dir) { file_put_contents($dir.'/api-route-verification.json', json_encode(['status' => 'Passed', 'executed_at_utc' => gmdate('c'), 'operations_checked' => count($checked), 'operations' => $checked], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); }
echo 'Laravel route registry matches '.count($checked).' documented operations.'.PHP_EOL;
