<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection('mysql');
if ($db->getDatabaseName() !== 'airline_scope_copy' || ! app()->environment('local', 'testing')) {
    throw new RuntimeException('Restored copy only');
}
$path = storage_path('app/private/scope-browser-original-passwords.json');
$aliases = ['data_admin', 'data_admin.2', 'operations.han', 'ops_manager.han', 'crew_scheduler', 'passenger'];
$original = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
foreach ($aliases as $alias) {
    $user = $db->table('users')->where('email', 'demo.'.$alias.'@airline.test')->first() ?? throw new RuntimeException('Existing test actor missing');
    $original[$user->id] ??= $user->password;
}
file_put_contents($path, json_encode($original, JSON_THROW_ON_ERROR));
chmod($path, 0600);
foreach ($aliases as $alias) {
    $db->table('users')->where('email', 'demo.'.$alias.'@airline.test')->update(['password' => Hash::make(config('airline.demo_password'))]);
}
echo "Prepared passwords for six existing actors on isolated copy; roles unchanged.\n";
