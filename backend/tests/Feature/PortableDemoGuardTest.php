<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortableDemoGuardTest extends TestCase
{
    private function localDemo(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['payments.mode' => 'mock', 'payments.bank.mode' => 'mock', 'mail.mailers.smtp.host' => 'mailpit', 'airline.demo_password' => 'Testing-only-password-123']);
    }

    public function test_existing_business_data_cannot_be_replaced_by_demo_install(): void
    {
        $this->localDemo();
        $before = DB::connection('ops')->table('flights')->orderBy('id')->get()->toJson();
        $this->artisan('airline:demo-install')->expectsOutputToContain('Existing business data detected')->assertFailed();
        $this->assertSame($before, DB::connection('ops')->table('flights')->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::connection('ops')->table('demo_installations')->count());
    }

    public function test_production_and_live_payments_are_rejected(): void
    {
        $this->localDemo();
        config(['payments.mode' => 'gateway']);
        $this->artisan('airline:demo-install')->expectsOutputToContain('Requires local environment')->assertFailed();
        config(['payments.mode' => 'mock']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('airline:demo-install')->expectsOutputToContain('Requires local environment')->assertFailed();
        $this->assertSame(0, DB::connection('ops')->table('demo_installations')->count());
    }

    public function test_verify_does_not_claim_success_without_a_completed_installation(): void
    {
        $this->localDemo();
        $this->artisan('airline:demo-install --verify')->expectsOutputToContain('No completed portable demo installation')->assertFailed();
        $this->assertSame(0, DB::connection('ops')->table('demo_installations')->count());
    }
}
