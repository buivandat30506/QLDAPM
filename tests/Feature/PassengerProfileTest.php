<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\OperationalFlight;
use App\Models\OrderBooking;
use App\Models\OrderItem;
use App\Models\PassengerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Passenger\PassengerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PassengerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function account(string $code = 'CUSTOMER', string $type = 'self', string $scope = '*'): User
    {
        $user = User::factory()->create();
        $role = Role::where('role_code', $code)->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $type, 'scope_id' => $type === 'self' ? $user->id : $scope]);

        return $user;
    }

    private function payload(string $document = 'SYNTHETIC-P123'): array
    {
        return ['full_name' => 'Hành khách mô phỏng', 'id_card_passport' => $document, 'dob' => '1995-01-01', 'gender' => 'OTHER', 'phone' => '0900000000', 'email' => 'synthetic@example.test', 'is_self' => true, 'consent' => true];
    }

    private function profile(User $actor, string $document = 'SYNTHETIC-P123'): PassengerProfile
    {
        return app(PassengerProfileService::class)->save($actor, $this->payload($document));
    }

    public function test_uc18_create_edit_consent_and_private_fields(): void
    {
        $owner = $this->account();
        $this->actingAs($owner)->post('/portal/profiles', $this->payload())->assertRedirect();
        $profile = PassengerProfile::where('user_id', $owner->id)->firstOrFail();
        $this->assertDatabaseHas('passenger_consents', ['passenger_id' => $profile->id, 'purpose' => 'SERVICE', 'granted' => true, 'actor_id' => $owner->id]);
        $this->get('/portal/profiles')->assertOk()->assertSee('****P123')->assertDontSee('SYNTHETIC-P123')->assertDontSee('0900000000');
        $edit = $this->payload(); $edit['id_card_passport'] = null; unset($edit['consent']); $edit['full_name'] = 'Hành khách đã sửa';
        $this->put('/portal/profiles/'.$profile->id, $edit)->assertRedirect();
        $this->assertDatabaseHas('passenger_profiles', ['id' => $profile->id, 'full_name' => 'Hành khách đã sửa', 'id_card_passport' => 'SYNTHETIC-P123']);
        $this->post('/portal/profiles/'.$profile->id.'/consents', ['purpose' => 'SERVICE', 'granted' => false])->assertRedirect();
        $this->assertDatabaseCount('passenger_consents', 2);
        $this->get('/portal/profiles/'.$profile->id)->assertOk()->assertSee('Đã rút lại');
        $log = DB::table('audit_logs')->where('entity_type', 'passenger_profiles')->get()->toJson();
        $this->assertStringNotContainsString('SYNTHETIC-P123', $log);
        $this->assertStringNotContainsString('0900000000', $log);
    }

    public function test_uc18_guest_without_account_is_owned_by_creator_and_mass_assignment_is_ignored(): void
    {
        $owner = $this->account(); $other = $this->account();
        $payload = $this->payload(); $payload['is_self'] = false; $payload['user_id'] = $other->id; $payload['created_by'] = $other->id;
        $this->actingAs($owner)->post('/portal/profiles', $payload)->assertRedirect();
        $profile = PassengerProfile::firstOrFail();
        $this->assertNull($profile->user_id);
        $this->assertSame($owner->id, $profile->created_by);
        $this->get('/portal/profiles/'.$profile->id)->assertOk();
        $this->actingAs($other)->get('/portal/profiles/'.$profile->id)->assertNotFound();
    }

    public function test_uc18_denied_ownership_validation_unique_document_and_required_consent(): void
    {
        $owner = $this->account(); $other = $this->account();
        $profile = $this->profile($owner);
        $this->actingAs($other)->get('/portal/profiles')->assertOk()->assertDontSee('Hành khách mô phỏng');
        $this->putJson('/portal/profiles/'.$profile->id, $this->payload())->assertNotFound();
        $this->postJson('/portal/profiles/'.$profile->id.'/consents', ['purpose' => 'MARKETING', 'granted' => true])->assertNotFound();
        $this->postJson('/portal/profiles', $this->payload())->assertUnprocessable();
        $payload = $this->payload('NEW-DOCUMENT'); unset($payload['consent']);
        $this->postJson('/portal/profiles', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('passenger_profiles', 1);
        $this->assertDatabaseCount('passenger_consents', 1);
    }

    public function test_uc19_create_routes_internally_encrypts_details_and_allows_update_cancel(): void
    {
        $owner = $this->account(); $profile = $this->profile($owner);
        $this->actingAs($owner)->post('/portal/assistance', ['passenger_id' => $profile->id, 'category' => 'WHEELCHAIR', 'details' => 'PRIVATE-SUPPORT-CONTENT'])->assertRedirect();
        $request = AssistanceRequest::firstOrFail();
        $this->assertSame('CUSTOMER_CARE', $request->department);
        $this->assertSame('ROUTED', $request->status);
        $this->assertStringNotContainsString('PRIVATE-SUPPORT-CONTENT', DB::table('assistance_requests')->value('details'));
        $this->get('/portal/assistance/'.$request->id)->assertOk()->assertSee('PRIVATE-SUPPORT-CONTENT');
        $this->post('/portal/assistance/'.$request->id, ['action' => 'update', 'details' => 'PRIVATE-UPDATED'])->assertRedirect();
        $this->assertSame('PRIVATE-UPDATED', $request->fresh()->details);
        $this->postJson('/portal/assistance/'.$request->id, ['action' => 'complete', 'resolution' => 'Cannot self approve'])->assertNotFound();
        $this->post('/portal/assistance/'.$request->id, ['action' => 'cancel'])->assertRedirect();
        $this->assertSame('CANCELLED', $request->fresh()->status);
        $this->postJson('/portal/assistance/'.$request->id, ['action' => 'update', 'details' => 'Cannot change terminal'])->assertUnprocessable();
        $audit = DB::table('audit_logs')->where('entity_type', 'assistance_requests')->get()->toJson();
        $this->assertStringNotContainsString('PRIVATE-SUPPORT-CONTENT', $audit);
        $this->assertStringNotContainsString('PRIVATE-UPDATED', $audit);
    }

    public function test_uc19_staff_handoff_and_completion_and_other_passenger_denied(): void
    {
        $owner = $this->account(); $profile = $this->profile($owner);
        $this->actingAs($owner)->post('/portal/assistance', ['passenger_id' => $profile->id, 'category' => 'OTHER', 'details' => 'Private fixture'])->assertRedirect();
        $request = AssistanceRequest::firstOrFail();
        $this->actingAs($this->account())->get('/portal/assistance/'.$request->id)->assertNotFound();
        $this->get('/portal/assistance')->assertOk()->assertDontSee('Hành khách mô phỏng');
        $this->postJson('/portal/assistance', ['passenger_id' => $profile->id, 'category' => 'OTHER', 'details' => 'Forged'])->assertNotFound();
        $sales = $this->account('SALES', 'global');
        $this->actingAs($sales)->get('/portal/assistance')->assertOk()->assertSee('Hành khách mô phỏng');
        $this->post('/portal/assistance/'.$request->id, ['action' => 'route', 'department' => 'AIRPORT_SUPPORT'])->assertRedirect();
        $this->assertSame('AIRPORT_SUPPORT', $request->fresh()->department);
        $this->post('/portal/assistance/'.$request->id, ['action' => 'complete', 'resolution' => 'Đã phối hợp bộ phận nội bộ'])->assertRedirect();
        $this->assertSame('COMPLETED', $request->fresh()->status);
        $this->assertSame('Đã phối hợp bộ phận nội bộ', $request->fresh()->resolution);
    }

    public function test_uc19_flight_link_requires_valid_booking_and_airport_queue_is_scoped(): void
    {
        $owner = $this->account(); $profile = $this->profile($owner);
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $data = ['passenger_id' => $profile->id, 'flight_id' => $flight->id, 'category' => 'WHEELCHAIR', 'details' => 'Flight support'];
        $this->actingAs($owner)->postJson('/portal/assistance', $data)->assertUnprocessable();
        $booking = OrderBooking::create(['order_code' => 'ABC123', 'customer_user_id' => $owner->id, 'total_amount' => '1500000.00', 'order_status' => 'CONFIRMED', 'hold_expires_at' => now('UTC')->addMinutes(15)]);
        OrderItem::create(['order_id' => $booking->id, 'flight_id' => $flight->id, 'passenger_id' => $profile->id, 'item_type' => 'SEAT', 'amount' => '1500000.00']);
        $this->post('/portal/assistance', $data)->assertRedirect();
        $request = AssistanceRequest::firstOrFail();
        $this->assertSame('AIRPORT_SUPPORT', $request->department);
        $origin = $flight->route->origin_airport_code;
        $this->actingAs($this->account('AIRPORT_STAFF', 'airport', $origin))->get('/portal/assistance')->assertOk()->assertSee('Hành khách mô phỏng');
        $this->get('/portal/assistance/'.$request->id)->assertOk();
        $this->actingAs($this->account('AIRPORT_STAFF', 'airport', $origin === 'HAN' ? 'SGN' : 'HAN'))->get('/portal/assistance')->assertOk()->assertDontSee('Hành khách mô phỏng');
        $this->get('/portal/assistance/'.$request->id)->assertNotFound();
    }

    public function test_bootstrap_existing_account_preserves_password_and_grants_only_system_rights(): void
    {
        $user = User::factory()->create(); $password = $user->password;
        $this->artisan('access:bootstrap', ['username' => $user->username, 'email' => $user->email, '--existing' => true])->assertExitCode(0);
        $this->assertSame($password, $user->fresh()->password);
        $this->actingAs($user)->get('/administration/users')->assertOk();
        $this->postJson('/api/v1/core/maintenance/1/release')->assertForbidden();
        $this->artisan('access:scope', ['username' => $user->username, 'role' => 'AIRPORT_STAFF', '--type' => 'airport', '--id' => 'HAN'])->assertExitCode(0);
        $this->assertDatabaseHas('user_data_scopes', ['user_id' => $user->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']);
    }
}
