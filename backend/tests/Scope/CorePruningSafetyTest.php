<?php

namespace Tests\Scope;

use App\Support\CorePruning;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CorePruningSafetyTest extends TestCase
{
    public function test_compact_schema_and_workers_do_not_need_retired_tables(): void
    {
        if (DB::connection('ops')->getDatabaseName() !== CorePruning::TARGET) {
            throw new \RuntimeException('Core copy only');
        }
        foreach (CorePruning::DROP as $node => $tables) {
            foreach ($tables as $table) {
                $this->assertFalse(Schema::connection($node)->hasTable($table), "$node.$table");
            }
        }
        $this->assertSame(10, DB::connection('ops')->table('infrastructure_types')->count());
        foreach (['ops', 'mysql', 'finance'] as $node) {
            $this->assertSame(0, DB::connection($node)->table('outbox')->whereNull('delivered_at')->count());
        }
        $this->artisan('airline:relay')->expectsOutput('Outbox delivery errors: 0')->assertSuccessful();
        $this->artisan('airline:demo-install', ['--verify' => true])->assertSuccessful();
        $this->assertFalse(Schema::connection('ops')->hasTable('devices'));
    }

    public function test_pruner_refuses_original_schema_and_production_before_mutation(): void
    {
        $db = DB::connection('ops');
        $name = $db->getDatabaseName();
        try {
            $db->setDatabaseName('airline');
            try {
                CorePruning::guard();
                $this->fail('Original database accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Refusing', $e->getMessage());
            }
        } finally {
            $db->setDatabaseName($name);
        }
        $this->app->detectEnvironment(fn () => 'production');
        try {
            CorePruning::guard();
            $this->fail('Production accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Local/testing', $e->getMessage());
        }
    }
}
