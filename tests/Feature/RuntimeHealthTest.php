<?php

namespace Tests\Feature;

use App\Jobs\RuntimeHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RuntimeHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_checks_mysql_and_real_worker_heartbeat(): void
    {
        config(['queue.default' => 'database']);
        $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
        Cache::put('runtime:scheduler', now('UTC')->toIso8601String(), 300);
        (new RuntimeHeartbeat(now('UTC')->toIso8601String()))->handle();
        $status = config('database.default') === 'mysql' ? 200 : 503;
        $response = $this->getJson('/health/ready')->assertStatus($status);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->travel(151)->seconds();
        $this->getJson('/health/ready')->assertStatus(503);
    }

    public function test_database_failure_does_not_expose_connection_or_stack_details(): void
    {
        $previous = config('database.default');
        config(['database.default' => 'broken', 'database.connections.broken' => ['driver' => 'DO_NOT_EXPOSE_DRIVER_SECRET']]);
        try {
            $this->getJson('/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable'])->assertDontSee('DO_NOT_EXPOSE');
        } finally {
            config(['database.default' => $previous]);
        }
    }
}
