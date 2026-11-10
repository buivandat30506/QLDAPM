<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Booking\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationModuleDisplayTest extends TestCase
{
    private function reservation(User $user): object
    {
        return app(ReservationService::class)->hold($user, ['trip_type' => 'ONE_WAY', 'idempotency_key' => (string) Str::uuid(), 'passengers' => [['name' => 'Hành khách kiểm thử']], 'segments' => [['flight_id' => 1, 'fare_class' => 'ECONOMY']]]);
    }

    public function test_non_empty_reservation_list_uses_real_ticket_status(): void
    {
        $u = User::factory()->create(['role' => 'passenger']);
        $r = $this->reservation($u);
        DB::table('bookings')->where('reservation_id', $r->id)->update(['status' => 'CONFIRMED']);
        $this->actingAs($u)->get('/modules/m2?flow=bookings')->assertOk()->assertSee('Đã xuất vé')->assertViewHas('reservations', fn ($rows) => $rows->first()->status === 'CONFIRMED');
        $this->get('/reservations/'.$r->id)->assertOk()->assertViewHas('status', 'CONFIRMED');
    }

    public function test_reservation_status_display_keeps_user_and_airport_scope(): void
    {
        $u = User::factory()->create(['role' => 'passenger']);
        $other = User::factory()->create(['role' => 'passenger']);
        $r = $this->reservation($u);
        $this->reservation($other);
        DB::table('reservations')->where('id', $r->id)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($u)->get('/modules/m2?flow=bookings')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->count() === 1 && $rows->first()->status === 'EXPIRED');
        $agent = User::factory()->create(['role' => 'airport_agent', 'airport_code' => 'SGN']);
        $this->actingAs($agent)->get('/modules/m2?flow=bookings')->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->isEmpty());
    }
}
