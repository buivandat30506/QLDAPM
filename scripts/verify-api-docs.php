<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$ids = [];
foreach (['foundation.openapi.yaml', 'flight-planning.openapi.yaml', 'flight-operations.openapi.yaml', 'fleet-crew.openapi.yaml', 'maintenance.openapi.yaml'] as $document) {
$api = Yaml::parseFile($root.'/docs/api/'.$document);
$resolve = function (mixed $node) use (&$resolve, $api): void {
    if (! is_array($node)) { return; }
    if (isset($node['$ref'])) {
        if (! str_starts_with($node['$ref'], '#/')) { throw new RuntimeException('Only local OpenAPI references are expected.'); }
        $target = $api;
        foreach (explode('/', substr($node['$ref'], 2)) as $part) {
            if (! is_array($target) || ! array_key_exists($part, $target)) { throw new RuntimeException('Broken OpenAPI reference: '.$node['$ref']); }
            $target = $target[$part];
        }
    }
    foreach ($node as $child) { $resolve($child); }
};
$resolve($api);
foreach ($api['paths'] as $path => $item) {
    preg_match_all('/\{([^}]+)\}/', $path, $matches);
    foreach ($item as $verb => $operation) {
        if (! in_array($verb, ['get', 'post', 'put', 'delete', 'patch'])) { continue; }
        if (isset($ids[$operation['operationId']])) { throw new RuntimeException('Duplicate operation ID.'); }
        $ids[$operation['operationId']] = true;
        $parameters = [...($item['parameters'] ?? []), ...($operation['parameters'] ?? [])];
        $names = [];
        foreach ($parameters as $parameter) {
            if (isset($parameter['$ref'])) { $parameter = $api['components']['parameters'][basename($parameter['$ref'])]; }
            if ($parameter['in'] === 'path' && ($parameter['required'] ?? false)) { $names[] = $parameter['name']; }
        }
        if (array_diff($matches[1], $names)) { throw new RuntimeException('Missing required path parameter: '.$path); }
        if (empty($operation['responses'])) { throw new RuntimeException('Missing responses: '.$path); }
    }
}
}
$compose = Yaml::parseFile($root.'/compose.yaml');
foreach (['db', 'init', 'app', 'worker', 'scheduler'] as $service) {
    if (! isset($compose['services'][$service])) { throw new RuntimeException('Missing Compose service.'); }
}
echo 'OpenAPI YAML/local references/path parameters: '.count($ids).' operations checked. Compose YAML parsed; container build/runtime Not Run.'.PHP_EOL;
