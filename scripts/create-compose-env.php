<?php

$file = dirname(__DIR__).'/.env.docker';
if (is_file($file)) {
    throw new RuntimeException('Existing .env.docker was preserved. Reuse it; rotating the key would break encrypted data.');
}
$contents = 'AIRLINE_APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL
    .'AIRLINE_DB_PASSWORD='.bin2hex(random_bytes(24)).PHP_EOL
    .'AIRLINE_DB_ROOT_PASSWORD='.bin2hex(random_bytes(24)).PHP_EOL;
$handle = fopen($file, 'x');
if (! $handle) {
    throw new RuntimeException('Cannot create private compose configuration.');
}
fwrite($handle, $contents);
fclose($handle);
@chmod($file, 0600);
echo "Created private .env.docker; credentials/key were not printed. Preserve this file with your private backups.\n";
