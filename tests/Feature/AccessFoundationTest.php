<?php

namespace Tests\Feature;

use App\Models\BaggageTag;
use App\Models\OperationalFlight;
use App\Models\OrderBooking;
use App\Models\OrderItem;
use App\Models\PassengerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Administration\AdministrationService;
use App\Services\Core\AuditService;
use App\Shared\Security\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function grant(string $code, string $type = 'global', string $scope = '*'): User
    {
        $user = User::factory()->create();
        $role = Role::where('role_code', $code)->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $type, 'scope_id' => $type === 'self' ? $user->id : $scope]);

        return $user;
    }

    public static function roleCases(): array
    {
        return [
            'admin' => ['ADMIN', 'users.manage', 'maintenance.approve'],
            'data' => ['DATA_ADMIN', 'airport.write', 'users.manage'],
            'operations' => ['FLIGHT_OPS', 'flight.write', 'airport.write'],
            'fleet' => ['FLEET_MANAGER', 'aircraft.write', 'maintenance.approve'],
            'maintenance' => ['TECHNICIAN', 'maintenance.approve', 'users.manage'],
            'crew' => ['CREW_OPS', 'crew.approve', 'aircraft.write'],
            'airport' => ['AIRPORT_STAFF', 'boarding.write', 'users.manage'],
            'sales' => ['SALES', 'booking.write', 'maintenance.approve'],
            'safety' => ['SAFETY_SECURITY', 'audit.read', 'users.manage'],
            'passenger' => ['CUSTOMER', 'booking.read', 'boarding.write'],
        ];
    }

    #[DataProvider('roleCases')]
    public function test_source_roles_allow_only_explicit_permissions(string $role, string $allowed, string $denied): void
    {
        $user = $this->grant($role, $role === 'CUSTOMER' ? 'self' : 'global');
        $access = app(AccessControl::class);
        $this->assertTrue($access->allows($user, $allowed, ['self' => $user->id]));
        $this->assertFalse($access->allows($user, $denied, ['self' => $user->id]));
    }

    public function test_ground_finance_unknown_permissions_default_deny(): void
    {
        foreach (['GROUND_SERVICES', 'FINANCE'] as $role) {
            $user = $this->grant($role);
            $this->assertFalse(app(AccessControl::class)->allows($user, 'users.manage'));
            $this->assertFalse(app(AccessControl::class)->allows($user, 'finance.approve'));
        }
        $this->assertCount(13, Role::all());
    }

    public function test_admin_cannot_release_and_role_without_scope_cannot_write(): void
    {
        $admin = $this->grant('ADMIN');
        $this->actingAs($admin)->postJson('/api/v1/core/maintenance/1/release')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'ACCESS_DENIED', 'outcome' => 'DENIED']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_code', 'DATA_ADMIN')->value('id'));
        $this->actingAs($user)->postJson('/api/v1/core/airports', [])->assertForbidden();
    }

    public function test_revocation_ignores_loaded_role_cache(): void
    {
        $user = $this->grant('DATA_ADMIN');
        $this->assertTrue(app(AccessControl::class)->allows($user, 'airport.write'));
        $user->roles;
        $user->roles()->detach();
        $this->assertFalse(app(AccessControl::class)->allows($user, 'airport.write'));
    }

    public function test_passenger_endpoints_require_authentication(): void
    {
        foreach (['bookings', 'checkin', 'checkin/board', 'baggage', 'baggage/events'] as $path) {
            $this->postJson('/api/v1/passenger/'.$path, [])->assertUnauthorized();
        }
        $this->getJson('/api/v1/passenger/bookings/ABCDEF')->assertUnauthorized();
        $this->getJson('/api/v1/passenger/baggage/VN12345678')->assertUnauthorized();
    }

    private function fixture(User $owner): array
    {
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $passenger = PassengerProfile::create(['user_id' => $owner->id, 'id_card_passport' => 'SYNTHETIC-'.substr($owner->id, -8), 'full_name' => 'Hành khách giả lập', 'dob' => '1995-01-01', 'gender' => 'OTHER', 'phone' => '0900000000']);
        $booking = OrderBooking::create(['order_code' => 'ABC123', 'customer_user_id' => $owner->id, 'total_amount' => '1500000.00', 'order_status' => 'CONFIRMED', 'hold_expires_at' => now('UTC')->addMinutes(15)]);
        OrderItem::create(['order_id' => $booking->id, 'flight_id' => $flight->id, 'passenger_id' => $passenger->id, 'item_type' => 'SEAT', 'amount' => '1500000.00']);
        $tag = BaggageTag::create(['tag_code' => 'VN12345678', 'flight_id' => $flight->id, 'passenger_id' => $passenger->id, 'weight_kg' => '15.50', 'status' => 'CHECKED_IN']);

        return [$booking, $passenger, $flight, $tag];
    }

    public function test_owner_reads_but_other_passenger_and_admin_cannot_read_or_checkin(): void
    {
        $owner = $this->grant('CUSTOMER', 'self');
        [$booking, $passenger, $flight, $tag] = $this->fixture($owner);
        $this->actingAs($owner)->getJson('/api/v1/passenger/bookings/'.$booking->order_code)->assertOk()->assertDontSee($passenger->id_card_passport);
        $this->getJson('/api/v1/passenger/baggage/'.$tag->tag_code)->assertOk()->assertDontSee('0900000000');
        foreach ([$this->grant('CUSTOMER', 'self'), $this->grant('ADMIN')] as $other) {
            $this->actingAs($other)->getJson('/api/v1/passenger/bookings/'.$booking->order_code)->assertNotFound();
            $this->getJson('/api/v1/passenger/baggage/'.$tag->tag_code)->assertNotFound();
            $this->postJson('/api/v1/passenger/checkin', ['flight_id' => $flight->id, 'passenger_id' => $passenger->id, 'seat_number' => '12A'])->assertNotFound();
        }
        $this->assertDatabaseCount('checkin_records', 0);
    }

    public function test_airport_scope_stops_cross_airport_read_and_write(): void
    {
        [$booking, $passenger, $flight, $tag] = $this->fixture($this->grant('CUSTOMER', 'self'));
        $origin = $flight->route->origin_airport_code;
        $wrong = $origin === 'HAN' ? 'SGN' : 'HAN';
        $this->actingAs($this->grant('AIRPORT_STAFF', 'airport', $wrong))->getJson('/api/v1/passenger/baggage/'.$tag->tag_code)->assertNotFound();
        $this->postJson('/api/v1/passenger/baggage/events', ['tag_code' => $tag->tag_code, 'event_type' => 'CLAIMED', 'location' => 'SIM', 'custody_unit' => 'SIM'])->assertForbidden();
        $this->assertEquals('CHECKED_IN', $tag->fresh()->status);
        $this->actingAs($this->grant('AIRPORT_STAFF', 'airport', $origin))->getJson('/api/v1/passenger/baggage/'.$tag->tag_code)->assertOk();
    }

    public function test_customer_cannot_book_using_another_account_id(): void
    {
        $owner = $this->grant('CUSTOMER', 'self');
        $other = $this->grant('CUSTOMER', 'self');
        $this->actingAs($owner)->postJson('/api/v1/passenger/bookings', ['customer_user_id' => $other->id, 'flight_id' => OperationalFlight::first()->id, 'passengers' => [['id_card_passport' => 'SIM123', 'full_name' => 'Demo', 'dob' => '1995-01-01', 'gender' => 'OTHER']]])->assertForbidden();
        $this->assertDatabaseCount('order_bookings', 0);
    }

    public function test_delegation_scope_expiry_revoke_and_grantor_revocation(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $this->actingAs($this->grant('ADMIN'));
        $grantor = $this->grant('AIRPORT_STAFF', 'airport', 'HAN');
        $grantee = User::factory()->create();
        $id = app(AdministrationService::class)->delegate(['grantor_id' => $grantor->id, 'grantee_id' => $grantee->id, 'permission_id' => DB::table('permissions')->where('code', 'boarding.write')->value('id'), 'scope_type' => 'airport', 'scope_id' => 'HAN', 'starts_at' => now('UTC')->subMinute()->toDateTimeString(), 'ends_at' => now('UTC')->addMinute()->toDateTimeString(), 'reason' => 'Thay ca giả lập']);
        $access = app(AccessControl::class);
        $this->assertTrue($access->allows($grantee, 'boarding.write', ['airport' => 'HAN']));
        $this->assertFalse($access->allows($grantee, 'boarding.write', ['airport' => 'SGN']));
        $this->travel(1)->minutes();
        $this->assertFalse($access->allows($grantee, 'boarding.write', ['airport' => 'HAN']));
        $this->travelBack();
        DB::table('delegations')->where('id', $id)->update(['ends_at' => now('UTC')->addHour()]);
        $grantor->roles()->detach();
        $this->assertFalse($access->allows($grantee, 'boarding.write', ['airport' => 'HAN']));
        app(AdministrationService::class)->revoke($id);
        $this->assertNotNull(DB::table('delegations')->find($id)->revoked_at);
    }

    public function test_audit_redacts_nested_pii_and_has_correlation(): void
    {
        $this->actingAs(User::where('username', 'data_demo')->firstOrFail());
        $response = $this->postJson('/api/v1/core/airports', ['iata_code' => 'TST', 'name' => 'Sân bay mô phỏng', 'city' => 'Demo']);
        $response->assertCreated()->assertHeader('X-Correlation-ID');
        $this->assertDatabaseHas('audit_logs', ['entity_id' => 'TST', 'correlation_id' => $response->headers->get('X-Correlation-ID')]);
        AuditService::record('test', 'redaction', 'UPDATE', ['nested' => ['password' => 'NEVER-LOG', 'passport' => 'PRIVATE-PASSPORT']], ['email' => 'private@example.test', 'token_hash' => 'SECRET-HASH']);
        $row = DB::table('audit_logs')->where('entity_id', 'redaction')->first();
        foreach (['NEVER-LOG', 'PRIVATE-PASSPORT', 'private@example.test', 'SECRET-HASH'] as $secret) { $this->assertStringNotContainsString($secret, $row->before_data.$row->after_data); }
        $this->actingAs($this->grant('CUSTOMER', 'self'))->get('/administration/audit')->assertForbidden();
        $this->get('/administration/audit/export')->assertForbidden();
    }

    public function test_login_roles_and_token_revocation(): void
    {
        $token = $this->postJson('/api/v1/auth/login', ['username' => 'ops_demo', 'password' => 'Demo@12345'])->assertOk()->assertJsonPath('data.roles.0', 'FLIGHT_OPS')->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_local_reset_is_private_and_single_use(): void
    {
        Storage::fake('local');
        $user = User::where('username', 'ops_demo')->firstOrFail();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $files = Storage::disk('local')->files('reset-mail');
        $this->assertCount(1, $files);
        $message = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertEquals('local-simulation', $message['mode']);
        $token = basename(parse_url($message['reset_link'], PHP_URL_PATH));
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword12345', $user->fresh()->password));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_admin_ui_persists_scopes_and_audit_export_escapes_formulas(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->grant('ADMIN'))->get('/administration/users')->assertOk()->assertSee($target->username);
        $this->get('/administration/users/'.$target->id)->assertOk();
        $role = Role::where('role_code', 'AIRPORT_STAFF')->firstOrFail();
        $this->put('/administration/users/'.$target->id, ['full_name' => 'Nhân viên thử', 'status' => 'ACTIVE', 'assignments' => [['role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']]])->assertRedirect();
        $this->assertDatabaseHas('user_data_scopes', ['user_id' => $target->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']);
        AuditService::record('test', '=CMD()', 'SAFE_EXPORT');
        $this->get('/administration/audit')->assertOk();
        $csv = $this->get('/administration/audit/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=CMD()", $csv);
        $this->delete('/administration/audit/1')->assertNotFound();
    }

    public function test_inactive_account_and_login_throttle(): void
    {
        $user = User::where('username', 'ops_demo')->firstOrFail();
        $user->update(['status' => 'SUSPENDED']);
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'Demo@12345'])->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/core/flights')->assertForbidden();
        for ($i = 0; $i < 11; $i++) { $last = $this->postJson('/api/v1/auth/login', ['username' => 'nonexistent', 'password' => 'incorrect']); }
        $last->assertTooManyRequests();
    }

    public function test_password_change_checks_current_password_and_revokes_api_tokens(): void
    {
        $user = $this->grant('CUSTOMER', 'self');
        $user->createToken('before-change');
        $this->actingAs($user)->post('/account/password', ['current_password' => 'wrong', 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->post('/account/password', ['current_password' => 'password', 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword12345', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_legacy_browser_payment_and_boarding_cannot_claim_unverified_success(): void
    {
        $this->actingAs($this->grant('CUSTOMER', 'self'))->postJson('/api/v1/passenger/bookings/confirm', ['order_code' => 'ABCDEF'])->assertStatus(409)->assertJsonStructure(['code', 'message', 'errors', 'correlation_id', 'success', 'data'])->assertJsonPath('success', false);
        $flight = OperationalFlight::firstOrFail();
        $this->actingAs($this->grant('AIRPORT_STAFF'))->postJson('/api/v1/passenger/checkin/board', ['flight_id' => $flight->id, 'token_hash' => str_repeat('a', 64)])->assertStatus(409);
    }
}
