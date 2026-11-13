<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Operations\InfrastructureFixtures;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureStatusInitialization;
use App\Modules\Operations\SchedulePlanning;
use Carbon\Carbon;
use Database\Seeders\AirportDemoSeeder;
use Database\Seeders\AirportIdentifierSeeder;
use Database\Seeders\InfrastructureTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallDemo extends Command
{
    public const VERSION = 'complete-demo-v1';

    protected $signature = 'airline:demo-install {--verify : Check the installed dataset without changing it}';

    protected $description = 'Install the complete portable local demo on an empty database; preserve existing installations';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Local/testing installation only.');

            return self::FAILURE;
        }
        if (! Schema::connection('ops')->hasTable('devices')) {
            foreach (['airports', 'routes', 'flights', 'flight_seasons', 'airport_infrastructure'] as $table) {
                if (! Schema::connection('ops')->hasTable($table)) {
                    $this->error('Core schema is incomplete; no data was generated.');

                    return self::FAILURE;
                }
            }
            $this->info('Compact core dataset detected. Full-system generation skipped; existing accounts, schedules and bookings preserved.');

            return self::SUCCESS;
        }
        if (! app()->environment('local') || config('payments.mode') !== 'mock' || config('payments.bank.mode') !== 'mock' || ! in_array(config('mail.mailers.smtp.host'), ['mailpit', 'localhost', '127.0.0.1'], true)) {
            $this->error('Requires local environment, mock payments and local Mailpit.');

            return self::FAILURE;
        }
        $lock = Cache::lock('airline-complete-demo-install', 3600);
        if (! $lock->get()) {
            $this->error('Another demo installation is running.');

            return self::FAILURE;
        }
        try {
            $db = DB::connection('ops');
            $marker = $db->table('demo_installations')->find(self::VERSION);
            if ($this->option('verify')) {
                if (! $marker || $marker->status !== 'READY') {
                    throw new \RuntimeException('No completed portable demo installation.');
                }
                $this->line(json_encode($this->verifyDataset(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }
            if ($marker && $marker->status === 'READY') {
                $this->info('Demo already installed. Existing data, passwords and edits preserved.');
                $this->info('Use --verify to check current demo coverage; installation does not reset user changes.');

                return self::SUCCESS;
            }
            if (strlen(config('airline.demo_password') ?? '') < 16) {
                throw new \RuntimeException('Set LOCAL_DEMO_PASSWORD to at least 16 characters.');
            }
            if (! $marker) {
                foreach (['airports', 'routes', 'flights', 'airport_infrastructure', 'aircraft'] as $table) {
                    if ($db->table($table)->exists()) {
                        throw new \RuntimeException('Existing business data detected. Nothing was replaced. Use a new Compose project and an empty database for the portable demo.');
                    }
                }
                foreach (['users', 'bookings', 'inventory'] as $table) {
                    $q = DB::table($table);
                    if ($table === 'users') {
                        $q->where('role', '!=', 'admin');
                    }
                    if ($q->exists()) {
                        throw new \RuntimeException('Existing commerce data detected; installation refused.');
                    }
                }
                if (DB::connection('finance')->table('payments')->exists()) {
                    throw new \RuntimeException('Existing payment data detected; installation refused.');
                }
                $db->table('demo_installations')->insert(['id' => self::VERSION, 'run_id' => (string) Str::uuid(), 'status' => 'INSTALLING', 'created_at' => now(), 'updated_at' => now()]);
                $marker = $db->table('demo_installations')->find(self::VERSION);
            }
            (new InfrastructureTypeSeeder)->run();
            (new AirportDemoSeeder)->generate(fn ($s) => $this->info($s), storage_path('app/private/demo-install/'.$marker->run_id));
            (new AirportIdentifierSeeder)->synchronize(true);
            app(InfrastructureNormalizer::class)->apply();
            $actor = User::where('email', 'demo.data_admin@airline.test')->firstOrFail();
            $previous = config('infrastructure.local_fixtures_enabled');
            config(['infrastructure.local_fixtures_enabled' => true]);
            try {
                $result = app(InfrastructureFixtures::class)->runAll(true, false, $actor);
                if ($result['blocked']) {
                    throw new \RuntimeException('Infrastructure fixture preparation blocked.');
                }
            } finally {
                config(['infrastructure.local_fixtures_enabled' => $previous]);
            }
            app(InfrastructureStatusInitialization::class)->run(true);
            $previousDirectory = config('airline.demo_directory');
            config(['airline.demo_directory' => storage_path('app/private/demo-install/'.$marker->run_id)]);
            try {
                if ($this->call('airline:demo-reservations') !== self::SUCCESS) {
                    throw new \RuntimeException('Cabin and round-trip examples could not be installed.');
                }
            } finally {
                config(['airline.demo_directory' => $previousDirectory]);
            }
            $this->planning($actor);
            $previousM02 = config('m02.local_fixtures_enabled');
            config(['m02.local_fixtures_enabled' => true]);
            try {
                if ($this->call('airline:m02-demo', ['--key' => 'portable-m02-v1']) !== self::SUCCESS) {
                    throw new \RuntimeException('M02 workflow scenarios could not be prepared.');
                }
            } finally {
                config(['m02.local_fixtures_enabled' => $previousM02]);
            }
            $summary = $this->verifyDataset();
            $db->table('demo_installations')->where('id', self::VERSION)->update(['status' => 'READY', 'summary' => json_encode($summary), 'updated_at' => now()]);
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Complete demo ready. Login: demo.admin@airline.test; password: LOCAL_DEMO_PASSWORD in backend/.env.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function planning(User $actor): void
    {
        $db = DB::connection('ops');
        $service = app(SchedulePlanning::class);
        $plan = $db->table('flight_seasons')->where('code', 'DEMO-M02-RECURRING')->first();
        if (! $plan) {
            $route = $db->table('routes')->where('origin', 'HAN')->where('destination', 'SGN')->first();
            $plane = $db->table('aircraft')->where('status', 'AVAILABLE')->where('type', 'A320')->firstOrFail();
            $gate = $db->table('airport_resources')->where('airport_code', 'HAN')->where('type', 'GATE')->where('active', true)->orderBy('code')->value('code');
            $plan = $service->save($actor, ['code' => 'DEMO-M02-RECURRING', 'number' => 'AS9801', 'route_id' => $route->id, 'aircraft_id' => $plane->id, 'from_date' => now('Asia/Ho_Chi_Minh')->addDays(30)->toDateString(), 'to_date' => now('Asia/Ho_Chi_Minh')->addDays(120)->toDateString(), 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'local_departure' => '10:00', 'duration_minutes' => 135, 'sale_price' => 1590000, 'gate' => $gate, 'status' => 'DRAFT']);
        }
        $service->generate($actor, $plan->id, $plan->from_date, Carbon::parse($plan->from_date)->addDay()->toDateString(), $plan->version);
    }

    private function verifyDataset(): array
    {
        $db = DB::connection('ops');
        $coverage = $db->table('airport_infrastructure')->where('active', true)->selectRaw('airport_code, COUNT(DISTINCT type_code) AS types')->groupBy('airport_code')->pluck('types', 'airport_code');
        $airports = $db->table('airports')->pluck('code');
        if ($airports->count() !== 22 || $airports->contains(fn ($a) => (int) ($coverage[$a] ?? 0) !== 10)) {
            throw new \RuntimeException('Expected all 22 airports to have all 10 infrastructure types.');
        }
        if ($db->table('airport_infrastructure')->whereNull('technical_state')->exists()) {
            throw new \RuntimeException('Infrastructure status initialization incomplete.');
        }
        if ($db->table('flights')->where('departure', '>', now())->where('status', 'SCHEDULED')->count() < 100) {
            throw new \RuntimeException('Demo timetable has expired or is incomplete; this command does not reset user data.');
        }
        if ($db->table('flight_season_occurrences')->count() < 2 || DB::table('bookings')->count() < 100) {
            throw new \RuntimeException('Demo scheduling or booking data incomplete.');
        }

        return ['version' => self::VERSION, 'airports' => $airports->count(), 'routes' => $db->table('routes')->count(), 'infrastructure_types' => $db->table('infrastructure_types')->count(), 'infrastructure' => $db->table('airport_infrastructure')->count(), 'airport_coverage' => $coverage, 'flights' => $db->table('flights')->count(), 'aircraft' => $db->table('aircraft')->count(), 'crew_assignments' => $db->table('crew_assignments')->count(), 'allocations' => $db->table('resource_allocations')->count(), 'recurring_plans' => $db->table('flight_seasons')->count(), 'generated_flights' => $db->table('flight_season_occurrences')->count(), 'bookings' => DB::table('bookings')->count(), 'payments' => DB::connection('finance')->table('payments')->count()];
    }
}
