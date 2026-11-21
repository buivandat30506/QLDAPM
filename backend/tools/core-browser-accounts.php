<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection('mysql');
if ($db->getDatabaseName() !== 'airline_core_acceptance_20261113' || ! app()->environment('local', 'testing')) {
    throw new RuntimeException('Browser acceptance schema only; candidate and original credentials are protected');
}
foreach (['data_admin', 'data_admin.2', 'operations.han', 'ops_manager.han', 'crew_scheduler', 'passenger'] as $alias) {
    $user = $db->table('users')->where('email', 'demo.'.$alias.'@airline.test')->first() ?? throw new RuntimeException('Missing existing actor');
    $db->table('users')->where('id', $user->id)->update(['password' => Hash::make(config('airline.demo_password'))]);
}
echo "Prepared six browser actors only on disposable acceptance schema; IDs and roles unchanged.\n";
