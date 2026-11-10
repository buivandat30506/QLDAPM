<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Support\AirportWorkspace;
use App\Support\StaffNavigation;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AirportWorkspaceTest extends TestCase
{
    private function user(string $role = 'data_admin'): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => 'HAN']);
    }

    public function test_existing_airports_remain_unknown_and_real_search_is_preserved(): void
    {
        $this->assertSame(0, DB::connection('ops')->table('airports')->whereNotNull('operational_status')->count());
        DB::connection('ops')->table('airport_resources')->where('airport_code', 'HAN')->update(['active' => false]);
        $this->actingAs($this->user())->get('/workspace/catalog?flow=airports&q=HAN')->assertOk()
            ->assertViewHas('airports', fn ($rows) => $rows->total() === 1 && $rows->first()->code === 'HAN' && $rows->first()->operational_status === null)
            ->assertSee('Chưa xác định')->assertSee('/workspace/catalog/airports/HAN?');
        $this->get('/workspace/catalog?flow=airports&q=DOES-NOT-EXIST')->assertOk()->assertSee('Không tìm thấy sân bay');
    }

    public function test_manual_status_is_validated_audited_and_does_not_change_other_business_data(): void
    {
        $user = $this->user();
        \App\Support\AirportStatusAuthority::assign($user, 'airport_director', 'HAN', 'Bổ nhiệm trong kiểm thử');
        $this->actingAs($user);
        $active = DB::connection('ops')->table('airports')->where('code', 'HAN')->value('active');
        $flightHash = hash('sha256', json_encode(DB::connection('ops')->table('flights')->orderBy('id')->get()));
        foreach (array_keys(AirportWorkspace::STATUSES) as $status) {
            $this->postJson('/workspace/catalog/airports/HAN/status', ['operational_status' => $status, 'note' => 'Căn cứ kiểm tra vận hành', 'active' => false, 'confirmed' => true])->assertOk()->assertJsonPath('status', $status);
            $record = DB::connection('ops')->table('airports')->where('code', 'HAN')->first();
            $this->assertSame($status, $record->operational_status);
            $this->assertSame($active, $record->active);
            $this->assertSame($user->id, $record->operational_status_updated_by);
            $this->assertNotNull($record->operational_status_updated_at);
        }
        $this->assertSame(4, DB::connection('ops')->table('audit_logs')->where('action', 'airport.operational_status.updated')->where('object_id', 'HAN')->count());
        $this->assertSame($flightHash, hash('sha256', json_encode(DB::connection('ops')->table('flights')->orderBy('id')->get())));
        $this->postJson('/workspace/catalog/airports/HAN/status', ['operational_status' => 'CLOSED', 'note' => 'Không hợp lệ'])->assertUnprocessable();
        $this->postJson('/workspace/catalog/airports/HAN/status', ['operational_status' => 'HIGH'])->assertUnprocessable();
    }

    public function test_airport_detail_returns_real_fields_and_preserves_list_context(): void
    {
        DB::connection('ops')->table('airports')->where('code', 'HAN')->update(['icao' => 'VVNB', 'country_code' => 'VN', 'latitude' => 21.221, 'longitude' => 105.807, 'operating_hours' => json_encode(['open' => '00:00', 'close' => '23:59'])]);
        $this->actingAs($this->user())->get('/workspace/catalog/airports/HAN?flow=airports&q=Ha&page=2')->assertOk()
            ->assertViewHas('airport', fn ($airport) => $airport->code === 'HAN' && $airport->icao === 'VVNB')
            ->assertViewHas('context', ['q' => 'Ha', 'page' => '2', 'flow' => 'airports'])
            ->assertSee('VVNB')->assertSee('105.807')->assertSee('UTC+07:00')->assertSee('Mở cửa')->assertSee('Đóng cửa')->assertSee('00:00')->assertSee('23:59')
            ->assertSee('flow=infrastructure&amp;airport=HAN', false);
        $this->get('/workspace/catalog/airports/ZZZ')->assertNotFound()->assertSee('Không tìm thấy dữ liệu');
    }

    public function test_infrastructure_requires_airport_and_filters_before_pagination(): void
    {
        $this->actingAs($this->user());
        $this->get('/workspace/catalog?flow=infrastructure')->assertOk()->assertViewHas('items', null)->assertViewHas('airport', null)->assertSee('Chọn sân bay cần xem');
        for ($index = 0; $index < 15; $index++) {
            DB::connection('ops')->table('airport_resources')->insert(['airport_code' => 'HAN', 'code' => 'M01-'.$index, 'type' => 'GATE', 'active' => $index !== 0]);
        }
        DB::connection('ops')->table('airport_resources')->insert(['airport_code' => 'SGN', 'code' => 'M01-OTHER', 'type' => 'GATE', 'active' => true]);
        $response = $this->get('/workspace/catalog?flow=infrastructure&airport=HAN&q=M01-&kind=GATE&page=2')->assertOk();
        $response->assertViewHas('items', fn ($items) => $items->total() === 15 && $items->count() === 3 && $items->every(fn ($row) => $row->airport_code === 'HAN'));
        $this->assertStringContainsString('airport=HAN', $response->viewData('items')->url(1));
        $this->assertStringContainsString('flow=infrastructure', $response->viewData('items')->url(1));
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN&q=M01-0')->assertOk()->assertSee('Ngừng hiệu lực')->assertDontSee('M01-OTHER');
        $this->get('/workspace/catalog?flow=infrastructure&airport=SGN&q=M01-')->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->code === 'M01-OTHER');
        $this->get('/workspace/catalog?flow=infrastructure&airport=ZZZ')->assertNotFound();
        $this->get('/workspace/catalog?flow=infrastructure&airport[]=HAN')->assertSessionHasErrors('airport');
    }

    public function test_area_parent_relationship_and_resource_details_are_real_and_isolated(): void
    {
        $db = DB::connection('ops');
        $terminal = $db->table('airport_areas')->insertGetId(['airport_code' => 'HAN', 'code' => 'REAL-T1', 'name' => 'Nhà ga có thật', 'type' => 'TERMINAL', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $belt = $db->table('airport_areas')->insertGetId(['airport_code' => 'HAN', 'parent_id' => $terminal, 'code' => 'BELT-1', 'name' => 'Băng chuyền số 1', 'type' => 'BELT', 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->user())->get('/workspace/catalog?flow=infrastructure&airport=HAN&source=areas&kind=BELT')->assertOk()->assertSee('Nhà ga có thật')->assertSee('Băng chuyền số 1')->assertSee('Ngừng hiệu lực');
        $this->get('/workspace/catalog/infrastructure/areas/'.$belt)->assertOk()->assertViewHas('parent', fn ($parent) => $parent->id === $terminal);
        $resource = $db->table('airport_resources')->where('airport_code', 'HAN')->first();
        $this->get('/workspace/catalog/infrastructure/resources/'.$resource->id)->assertOk()->assertViewHas('record', fn ($record) => $record->id === $resource->id)->assertViewHas('context', ['flow' => 'infrastructure', 'airport' => 'HAN', 'source' => 'resources']);
        $this->get('/workspace/catalog/infrastructure/resources/999999')->assertNotFound();
    }

    public function test_edits_use_route_identity_and_independent_approval_without_overwriting_status(): void
    {
        $author = $this->user();
        $reviewer = $this->user();
        $response = $this->actingAs($author)->postJson('/workspace/catalog/airports/HAN/changes', ['name' => 'Nội Bài cập nhật', 'city' => 'Hà Nội', 'timezone' => 'Asia/Ho_Chi_Minh', 'reason' => 'Chuẩn hóa dữ liệu sân bay', 'record_id' => 'SGN'])->assertCreated();
        $id = $response->json('id');
        $this->assertSame('HAN', DB::connection('ops')->table('master_changes')->where('id', $id)->value('record_id'));
        $this->postJson('/api/v1/actions/master_approve', ['change_id' => $id])->assertConflict();
        \App\Support\AirportStatusAuthority::assign($author, 'airport_director', 'HAN', 'Bổ nhiệm trong kiểm thử');
        $this->postJson('/workspace/catalog/airports/HAN/status', ['operational_status' => 'HIGH', 'note' => 'Ghi nhận thủ công độc lập', 'confirmed' => true])->assertOk();
        $this->actingAs($reviewer)->postJson('/api/v1/actions/master_approve', ['change_id' => $id])->assertOk();
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'name' => 'Nội Bài cập nhật', 'operational_status' => 'HIGH'], 'ops');
    }

    public function test_resource_edit_stays_a_proposal_and_rejects_disabling_an_allocated_resource(): void
    {
        $author = $this->user();
        $reviewer = $this->user();
        $db = DB::connection('ops');
        $resource = $db->table('airport_resources')->where('airport_code', 'HAN')->where('type', 'GATE')->first();
        $flight = $db->table('flights')->first();
        $db->table('resource_allocations')->insert(['flight_id' => $flight->id, 'resource_id' => $resource->id, 'starts_at' => now(), 'ends_at' => now()->addHours(2), 'actor_id' => $author->id, 'reason' => 'Phân bổ đang hiệu lực', 'created_at' => now(), 'updated_at' => now()]);
        $id = $this->actingAs($author)->postJson('/workspace/catalog/infrastructure/resources/'.$resource->id.'/changes', ['active' => false, 'reason' => 'Đề nghị dừng tài nguyên'])->assertCreated()->json('id');
        $this->assertDatabaseHas('airport_resources', ['id' => $resource->id, 'active' => true], 'ops');
        $this->actingAs($reviewer)->postJson('/api/v1/actions/master_approve', ['change_id' => $id])->assertConflict();
    }

    public function test_airport_proposals_captured_before_status_migration_can_still_be_approved(): void
    {
        $author = $this->user();
        $reviewer = $this->user();
        $before = (array) DB::connection('ops')->table('airports')->where('code', 'HAN')->first();
        foreach (['operational_status', 'operational_status_note', 'operational_status_updated_by', 'operational_status_updated_at'] as $column) {
            unset($before[$column]);
        }
        $id = DB::connection('ops')->table('master_changes')->insertGetId([
            'entity' => 'airports', 'record_id' => 'HAN', 'before_data' => json_encode($before),
            'after_data' => json_encode(['name' => 'Hồ sơ phiên bản cũ', 'city' => $before['city'], 'timezone' => $before['timezone']]),
            'reason' => 'Đề nghị lập trước migration', 'created_by' => $author->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($reviewer)->postJson('/api/v1/actions/master_approve', ['change_id' => $id])->assertOk();
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'name' => 'Hồ sơ phiên bản cũ', 'operational_status' => null], 'ops');
    }

    public function test_permissions_and_navigation_are_preserved(): void
    {
        $unauthorized = $this->user('operations');
        $before = array_map(fn ($role) => Access::allows($unauthorized, $role), Access::ROLES);
        $this->actingAs($unauthorized)->get('/workspace/catalog?flow=airports')->assertOk()->assertViewHas('airports', fn ($rows) => $rows->total() === 1 && $rows->first()->code === 'HAN');
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertForbidden();
        $this->get('/workspace/catalog/airports/HAN')->assertOk()->assertDontSee('data-airport-status-form', false);
        $this->get('/workspace/catalog/airports/SGN')->assertNotFound();
        $this->postJson('/workspace/catalog/airports/HAN/status', ['operational_status' => 'NORMAL', 'note' => 'Không có quyền'])->assertForbidden();
        $this->postJson('/workspace/catalog/airports/HAN/changes', [])->assertForbidden();
        $this->assertSame($before, array_map(fn ($role) => Access::allows($unauthorized, $role), Access::ROLES));
        $admin = $this->user('admin');
        foreach (['/workspace/catalog/airports/HAN' => 'Danh sách sân bay', '/workspace/catalog/infrastructure/resources/1' => 'Cơ sở hạ tầng sân bay'] as $url => $label) {
            $this->assertSame($label, StaffNavigation::context($admin, Request::create($url))['label']);
        }
    }

    public function test_database_read_failure_has_a_retry_page_without_database_details(): void
    {
        $this->actingAs($this->user());
        $database = config('database.connections.ops.database');
        try {
            config(['database.connections.ops.database' => 'missing_m01_read_test']);
            DB::purge('ops');
            $this->get('/workspace/catalog?flow=airports')->assertStatus(503)->assertViewIs('m01.load-error')->assertSee('Thử tải lại')->assertDontSee('SQLSTATE');
        } finally {
            config(['database.connections.ops.database' => $database]);
            DB::purge('ops');
        }
    }

    public function test_imported_airports_remain_visible_and_can_be_approved_before_a_detail_record_exists(): void
    {
        $author = $this->user();
        $reviewer = $this->user();
        $file = UploadedFile::fake()->createWithContent('m01.csv', "code,name,city,timezone\nZZZ,Sân bay nhập thử,Thành phố thử,Asia/Ho_Chi_Minh\n");
        $this->actingAs($author)->post('/workspace/master/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();
        $this->get('/workspace/catalog?flow=airports')->assertOk()
            ->assertViewHas('pendingImports', fn ($imports) => $imports->total() === 1 && $imports->first()->record_id === 'ZZZ')
            ->assertSee('Chờ người khác duyệt');
        $this->assertDatabaseMissing('airports', ['code' => 'ZZZ'], 'ops');
        $id = DB::connection('ops')->table('master_changes')->where('record_id', 'ZZZ')->value('id');
        $this->actingAs($reviewer)->get('/workspace/catalog?flow=airports')->assertOk()
            ->assertSee('name="change_id" value="'.$id.'"', false);
        $this->postJson('/api/v1/actions/master_approve', ['change_id' => $id])->assertOk();
        $this->assertDatabaseHas('airports', ['code' => 'ZZZ', 'operational_status' => null], 'ops');
        $this->get('/workspace/catalog?flow=airports')->assertOk()->assertViewHas('pendingImports', fn ($imports) => $imports->total() === 0);
    }
}
