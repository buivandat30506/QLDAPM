<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Creates a NEW database and a private runtime profile; never writes .env/APP_KEY.
$root = dirname(__DIR__);
$profile = $root.'/storage/app/private/demo-runtime.json';
if (is_file($profile)) {
    throw new RuntimeException('Demo profile already exists. Use scripts/demo.php; existing data/key were preserved.');
}
$host = getenv('DEMO_DB_HOST') ?: '127.0.0.1';
$port = getenv('DEMO_DB_PORT') ?: '3318';
$user = getenv('DEMO_DB_USER') ?: 'root';
$password = getenv('DEMO_DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = $pdo->query('SELECT VERSION() AS version, @@version_comment AS product')->fetch(PDO::FETCH_ASSOC);
if (stripos($engine['version'].' '.$engine['product'], 'MariaDB') !== false) {
    throw new RuntimeException('Demo profile requires MySQL/InnoDB.');
}
$database = 'airline_demo_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$environment = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => 'http://127.0.0.1:8000',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $password, 'DB_URL' => '',
    'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'APP_CONFIG_CACHE' => $root.'/storage/app/no-demo-config.php'];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if ($kernel->call('migrate', ['--force' => true]) !== 0 || $kernel->call('db:seed', ['--force' => true]) !== 0) {
    throw new RuntimeException('New demo setup failed; database retained: '.$database);
}
$accounts = [];
foreach (['demo_admin' => ['ADMIN'], 'demo_data' => ['DATA_ADMIN'],
    'demo_ops' => ['FLIGHT_OPS', 'FLEET_MANAGER', 'CREW_OPS', 'TECHNICIAN'], 'demo_customer' => ['CUSTOMER']] as $username => $roles) {
    $secret = Str::password(24);
    $account = User::create(['username' => $username, 'email' => $username.'@example.test', 'full_name' => 'Demo '.implode(' / ', $roles),
        'password' => Hash::make($secret), 'status' => 'ACTIVE']);
    foreach ($roles as $code) {
        $role = Role::where('role_code', $code)->firstOrFail();
        $account->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $account->id, 'role_id' => $role->id,
            'scope_type' => $code === 'CUSTOMER' ? 'self' : 'global', 'scope_id' => $code === 'CUSTOMER' ? $account->id : '*']);
    }
    $accounts[] = ['username' => $username, 'password' => $secret, 'roles' => $roles];
}
if (! is_dir(dirname($profile))) {
    mkdir(dirname($profile), 0700, true);
}
file_put_contents($profile, json_encode(['mode' => 'local-demo', 'database' => $database, 'created_at_utc' => gmdate('c'), 'engine' => $engine,
    'environment' => $environment, 'accounts' => $accounts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
@chmod($profile, 0600);
echo "Created isolated local demo: $database (MySQL {$engine['version']}).\n";
echo "Private runtime/key/credentials: storage/app/private/demo-runtime.json (never commit/share).\n";
echo "Start: php scripts/demo.php serve --host=127.0.0.1 --port=8000\n";
