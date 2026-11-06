<?php

namespace Tests\Feature;

use App\Models\AirportResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Core\MasterDataService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('username', 'data_demo')->firstOrFail());
    }

    public function test_airport_identifier_coordinates_timezone_and_status_are_validated_and_audited(): void
    {
        $payload = ['iata_code' => ' tst ', 'icao_code' => ' vvtz ', 'name' => 'Sân bay thử', 'city' => 'Địa điểm thử',
            'latitude' => '21.2212000', 'longitude' => '105.8072000', 'timezone' => 'America/New_York'];
        $this->postJson('/api/v1/core/airports', $payload)->assertCreated()->assertJsonPath('data.iata_code', 'TST')->assertJsonPath('data.icao_code', 'VVTZ');
        $this->postJson('/api/v1/core/airports', array_replace($payload, ['iata_code' => 'TSX']))->assertUnprocessable()->assertJsonValidationErrors('icao_code');
        foreach ([['timezone' => 'Mars/Local'], ['latitude' => 91], ['longitude' => 181], ['name' => ' ']] as $invalid) {
            $this->putJson('/api/v1/core/airports/TST', $invalid)->assertUnprocessable();
        }
        $this->putJson('/api/v1/core/airports/TST', ['status' => 'INACTIVE'])->assertOk();
        $this->putJson('/api/v1/core/airports/TST', ['iata_code' => 'CHG'])->assertUnprocessable();
        $this->assertDatabaseHas('airports', ['iata_code' => 'TST', 'status' => 'INACTIVE', 'timezone' => 'America/New_York']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'airports', 'entity_id' => 'TST', 'action' => 'UPDATED', 'actor_id' => auth()->id()]);
    }

    public function test_airport_scope_filters_lists_and_checks_objects_before_validation(): void
    {
        $user = User::factory()->create();
        $role = Role::where('role_code', 'DATA_ADMIN')->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']);
        $this->actingAs($user);
        $this->getJson('/api/v1/core/airports')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.iata_code', 'HAN');
        $this->get('/admin/airports')->assertOk()->assertSee('Nội Bài')->assertDontSee('Tân Sơn Nhất');
        $this->getJson('/api/v1/core/airports/SGN')->assertForbidden();
        $this->putJson('/api/v1/core/airports/SGN', ['name' => ''])->assertForbidden();
        $this->postJson('/api/v1/core/airports', ['iata_code' => 'TST'])->assertForbidden();
        $resource = AirportResource::where('airport_code', 'SGN')->firstOrFail();
        $this->putJson('/api/v1/core/airports/resources/'.$resource->id, ['status' => 'MAINTENANCE'])->assertForbidden();
        $this->deleteJson('/api/v1/core/airports/resources/'.$resource->id)->assertForbidden();
        $this->get('/admin/master-data')->assertForbidden();
        $this->get('/admin/master-data/export')->assertForbidden();
        $this->putJson('/api/v1/core/airports/HAN', ['status' => 'INACTIVE'])->assertOk();
        $this->get('/admin/airports/HAN')->assertOk()->assertSee('Tạm ngừng');
        $this->assertDatabaseHas('airports', ['iata_code' => 'SGN', 'status' => 'ACTIVE']);
    }

    public function test_mysql_constraints_reject_invalid_coordinates_even_without_service_validation(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL CHECK constraints are verified on the target engine.');
        }
        foreach ([['latitude' => 91, 'longitude' => 0], ['latitude' => 0, 'longitude' => 181], ['latitude' => null, 'longitude' => 1]] as $coordinates) {
            try {
                DB::table('airports')->insert($coordinates + ['iata_code' => 'CHK', 'name' => 'Constraint fixture', 'city' => 'Synthetic city', 'created_at' => now(), 'updated_at' => now()]);
                $this->fail('The MySQL coordinate CHECK must reject invalid data.');
            } catch (QueryException $e) {
                $this->assertSame(3819, (int) $e->errorInfo[1]);
            }
        }
        $this->assertDatabaseMissing('airports', ['iata_code' => 'CHK']);
    }

    public function test_all_source_resource_types_can_be_created_edited_and_suspended(): void
    {
        foreach (array_keys(AirportResource::TYPES) as $type) {
            $id = $this->postJson('/api/v1/core/airports/HAN/resources', ['resource_type' => $type, 'resource_code' => 'TEST-'.$type])->assertCreated()->json('data.id');
            $this->putJson('/api/v1/core/airports/resources/'.$id, ['status' => 'MAINTENANCE', 'airport_code' => 'SGN', 'resource_type' => 'UNKNOWN'])->assertOk();
            $this->assertDatabaseHas('airport_resources', ['id' => $id, 'resource_type' => $type, 'airport_code' => 'HAN', 'status' => 'MAINTENANCE']);
            $this->postJson('/api/v1/core/airports/HAN/resources', ['resource_type' => $type, 'resource_code' => ' TEST-'.$type.' '])->assertUnprocessable();
            $this->deleteJson('/api/v1/core/airports/resources/'.$id)->assertOk();
        }
        $this->postJson('/api/v1/core/airports/HAN/resources', ['resource_type' => 'GATE', 'resource_code' => '  '])->assertUnprocessable();
        $this->postJson('/api/v1/core/airports/HAN/resources', ['resource_type' => 'UNKNOWN', 'resource_code' => 'ANY'])->assertUnprocessable();
        $resource = AirportResource::where('resource_code', 'DEMO-G1')->firstOrFail();
        $this->deleteJson('/api/v1/core/airports/resources/'.$resource->id)->assertUnprocessable();
        $this->assertDatabaseHas('airport_resources', ['id' => $resource->id]);
        $this->getJson('/api/v1/core/airports/HAN/resources?type=GATE&resource_status=AVAILABLE&per_page=1')->assertOk()->assertJsonCount(1, 'data.data');
        $this->get('/admin/airports/HAN?resource_type=GATE&resource_status=AVAILABLE')->assertOk()->assertSee('Lịch sử thay đổi sân bay và tài nguyên');
    }

    public function test_master_schema_checks_missing_fields_duplicates_relations_and_timezone(): void
    {
        foreach ([['payload' => []], ['category' => 'UNKNOWN'], ['code' => '=FORMULA'], ['payload' => ['name' => 'X', 'timezone' => 'Mars/Local']],
            ['payload' => ['name' => 'X', 'password' => 'NO-STORE']], ['category' => 'SERVICE', 'payload' => ['name' => 'X', 'cabin_code' => 'NO-SUCH-CABIN']],
            ['category' => 'CURRENCY', 'payload' => ['name' => 'X', 'minor_units' => 6]]] as $invalid) {
            $this->postJson('/api/v1/core/master-data', array_replace($this->masterInput(), $invalid))->assertUnprocessable();
        }
        $this->postJson('/api/v1/core/master-data', $this->masterInput())->assertCreated();
        $this->postJson('/api/v1/core/master-data', array_replace($this->masterInput(), ['code' => ' test_cabin ']))->assertUnprocessable();
        $this->getJson('/api/v1/core/master-data?per_page=101')->assertUnprocessable();
    }

    public function test_master_revision_preserves_published_snapshot_checks_stale_changes_and_records_responsibility(): void
    {
        $actor = auth()->id();
        $id = $this->postJson('/api/v1/core/master-data', $this->masterInput())->assertCreated()->json('data.id');
        foreach (['submit', 'approve', 'publish'] as $step) {
            $this->postJson("/api/v1/core/master-data/$id/$step")->assertOk();
        }
        $this->assertDatabaseHas('master_data_records', ['id' => $id, 'version' => 1, 'submitted_by' => $actor, 'approved_by' => $actor, 'published_by' => $actor]);
        $original = DB::table('entity_versions')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->first()->snapshot;
        $this->postJson("/api/v1/core/master-data/$id/revise", ['reason' => 'Cập nhật tên hạng khoang'])->assertOk();
        $this->assertSame(1, app(MasterDataService::class)->published($id)->version);
        $revision = DB::table('master_data_records')->find($id)->revision;
        $input = array_replace($this->masterInput(), ['payload' => ['name' => 'Tên bản sửa đổi'], 'expected_revision' => $revision]);
        $this->putJson("/api/v1/core/master-data/$id", $input)->assertOk();
        $this->putJson("/api/v1/core/master-data/$id", $input)->assertConflict();
        $this->postJson("/api/v1/core/master-data/$id/submit")->assertOk();
        $this->postJson("/api/v1/core/master-data/$id/reject", ['reason' => 'Cần kiểm tra lại'])->assertOk();
        $this->assertDatabaseHas('master_data_records', ['id' => $id, 'approved_by' => null, 'status' => 'DRAFT']);
        foreach (['submit', 'approve', 'publish'] as $step) {
            $this->postJson("/api/v1/core/master-data/$id/$step")->assertOk();
        }
        $this->assertSame(2, app(MasterDataService::class)->published($id)->version);
        $this->assertSame($original, DB::table('entity_versions')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->where('version', 1)->value('snapshot'));
        $this->postJson("/api/v1/core/master-data/$id/publish")->assertUnprocessable();
        $this->get('/admin/master-data/'.$id)->assertOk()->assertSee('Phiên bản 2')->assertSee('Cần kiểm tra lại');
    }

    public function test_quality_is_rechecked_at_publication_and_stale_revision_is_rejected(): void
    {
        $id = $this->postJson('/api/v1/core/master-data', $this->masterInput())->json('data.id');
        $this->postJson("/api/v1/core/master-data/$id/submit", ['expected_revision' => 8])->assertConflict();
        foreach (['submit', 'approve'] as $step) {
            $this->postJson("/api/v1/core/master-data/$id/$step")->assertOk();
        }
        DB::table('master_data_records')->where('id', $id)->update(['payload' => json_encode(['name' => 'X', 'timezone' => 'Invalid/Timezone'])]);
        $this->postJson("/api/v1/core/master-data/$id/publish")->assertUnprocessable();
        $this->assertDatabaseHas('master_data_records', ['id' => $id, 'status' => 'APPROVED', 'version' => 0]);
        $this->assertDatabaseMissing('entity_versions', ['entity_type' => 'master_data_records', 'entity_id' => (string) $id]);
    }

    public function test_invalid_csv_formats_are_rejected_before_staging(): void
    {
        foreach ([['wrong.csv', "category,code\nCABIN,X\n"], ['wrong.json', '{}'], ['broken.csv', "category,code,payload\n\xFF\0"],
            ['unclosed.csv', "category,code,payload\nCABIN,X,\"{\"\"name\"\":\"\"X\"\"}\n"]] as [$name,$content]) {
            $this->postJson('/admin/master-data/imports', ['file' => UploadedFile::fake()->createWithContent($name, $content)])->assertUnprocessable();
        }
        $this->assertDatabaseCount('master_import_batches', 0);
    }

    public function test_mixed_invalid_duplicate_and_bad_relation_rows_cannot_partially_import(): void
    {
        $batch = $this->preview([$this->masterInput('GOOD'), $this->masterInput('GOOD'),
            ['category' => 'SERVICE', 'code' => 'BAD_REF', 'payload' => ['name' => 'X', 'cabin_code' => 'MISSING']],
            ['category' => 'CABIN', 'code' => 'MISSING_NAME', 'payload' => ['timezone' => 'UTC']]]);
        $this->assertDatabaseHas('master_import_batches', ['id' => $batch, 'status' => 'INVALID', 'row_count' => 4, 'error_count' => 3]);
        $this->postJson('/admin/master-data/imports/'.$batch.'/commit')->assertUnprocessable();
        $this->assertDatabaseMissing('master_data_records', ['code' => 'GOOD']);
        $this->get('/admin/master-data/imports/'.$batch)->assertOk()->assertSee('Không nhập bất kỳ dòng nào');
    }

    public function test_valid_csv_import_commit_is_atomic_idempotent_and_leaves_drafts(): void
    {
        $batch = $this->preview([$this->masterInput('BATCH_A'), $this->masterInput('BATCH_B')]);
        $this->assertDatabaseMissing('master_data_records', ['code' => 'BATCH_A']);
        $this->assertDatabaseHas('master_import_batches', ['id' => $batch, 'status' => 'READY']);
        $this->post('/admin/master-data/imports/'.$batch.'/commit')->assertRedirect();
        $this->post('/admin/master-data/imports/'.$batch.'/commit')->assertRedirect();
        $this->assertSame(2, DB::table('master_data_records')->whereIn('code', ['BATCH_A', 'BATCH_B'])->where('status', 'DRAFT')->count());
        $this->assertSame(2, DB::table('master_import_rows')->where('batch_id', $batch)->whereNotNull('record_id')->count());
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'master_import_batches', 'entity_id' => $batch, 'action' => 'IMPORT_COMMITTED']);
    }

    public function test_import_revalidates_database_changes_without_partial_writes_and_keeps_diagnostics(): void
    {
        $batch = $this->preview([$this->masterInput('GOOD_NEW'), $this->masterInput('NOW_EXISTS')]);
        $this->postJson('/api/v1/core/master-data', $this->masterInput('NOW_EXISTS'))->assertCreated();
        $this->postJson('/admin/master-data/imports/'.$batch.'/commit')->assertUnprocessable();
        $this->assertDatabaseMissing('master_data_records', ['code' => 'GOOD_NEW']);
        $this->assertDatabaseHas('master_import_batches', ['id' => $batch, 'status' => 'INVALID', 'error_count' => 1]);
        $this->assertStringContainsString('code', DB::table('master_import_rows')->where('batch_id', $batch)->where('line', 3)->value('errors'));
    }

    public function test_export_filters_escape_formula_cells_and_default_admin_has_no_data_authority(): void
    {
        $input = $this->masterInput('CSV_TEST');
        $input['payload']['name'] = '  =HYPERLINK("unsafe")';
        $this->postJson('/api/v1/core/master-data', $input)->assertCreated();
        $response = $this->get('/admin/master-data/export?search=CSV_TEST');
        $response->assertOk();
        $csv = $response->streamedContent();
        // Laravel trims request strings; the exported cell must still neutralize the formula.
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('ECONOMY', $csv);
        $user = User::factory()->create();
        $role = Role::where('role_code', 'ADMIN')->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => '*']);
        $this->actingAs($user)->get('/admin/master-data')->assertForbidden();
        $this->get('/admin/master-data/export')->assertForbidden();
        $this->postJson('/admin/master-data/imports', ['file' => UploadedFile::fake()->createWithContent('test.csv', 'wrong')])->assertForbidden();
    }

    public function test_web_create_edit_and_import_template_are_real_and_validation_errors_render(): void
    {
        $this->get('/admin/master-data')->assertOk()->assertSee('Nhập CSV');
        $this->get('/admin/master-data/template')->assertOk()->assertDownload('master-import-template.csv');
        $url = $this->post('/admin/master-data', ['category' => 'CABIN', 'code' => 'WEB_TEST', 'payload_json' => '{"name":"Tạo từ web"}'])->assertRedirect()->headers->get('Location');
        $this->get($url)->assertOk()->assertSee('Tạo từ web');
        $id = DB::table('master_data_records')->where('code', 'WEB_TEST')->value('id');
        $this->put('/admin/master-data/'.$id, ['category' => 'CABIN', 'code' => 'WEB_TEST', 'payload_json' => '{"name":"Đã sửa từ web"}', 'expected_revision' => 0])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Đã sửa từ web');
        $this->from($url)->put('/admin/master-data/'.$id, ['category' => 'CABIN', 'code' => 'WEB_TEST', 'payload_json' => '{"wrong":1}', 'expected_revision' => 1])->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('Đã xảy ra lỗi kiểm tra dữ liệu');
    }

    public function test_api_import_quality_published_snapshot_and_export_use_the_same_contract(): void
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['category', 'code', 'payload'], ',', '"', '');
        fputcsv($stream, ['CABIN', 'API_BATCH', '{"name":"API fixture"}'], ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $batch = $this->postJson('/api/v1/core/master-data/imports', ['file' => UploadedFile::fake()->createWithContent('api.csv', $csv)])
            ->assertCreated()->assertJsonPath('data.status', 'READY')->json('data.id');
        $this->getJson('/api/v1/core/master-data/imports/'.$batch)->assertOk()->assertJsonCount(1, 'data.rows.data');
        $this->postJson('/api/v1/core/master-data/imports/'.$batch.'/commit')->assertOk()->assertJsonPath('data.status', 'COMMITTED');
        $id = DB::table('master_data_records')->where('code', 'API_BATCH')->value('id');
        $this->getJson('/api/v1/core/master-data/'.$id)->assertOk()->assertJsonPath('data.quality_errors', []);
        $this->getJson('/api/v1/core/master-data/'.$id.'/published')->assertNotFound();
        foreach (['submit', 'approve', 'publish'] as $action) {
            $this->postJson('/api/v1/core/master-data/'.$id.'/'.$action)->assertOk();
        }
        $this->getJson('/api/v1/core/master-data/'.$id.'/published')->assertOk()->assertJsonPath('data.version', 1);
        $response = $this->get('/api/v1/core/master-data/export?search=API_BATCH')->assertOk();
        $this->assertStringContainsString('API_BATCH', $response->streamedContent());
    }

    private function masterInput(string $code = 'TEST_CABIN'): array
    {
        return ['category' => 'CABIN', 'code' => $code, 'payload' => ['name' => 'Hạng khoang thử nghiệm', 'timezone' => 'Asia/Ho_Chi_Minh']];
    }

    private function preview(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['category', 'code', 'payload'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, [$row['category'], $row['code'], json_encode($row['payload'], JSON_UNESCAPED_UNICODE)], ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $response = $this->post('/admin/master-data/imports',['file' => UploadedFile::fake()->createWithContent('synthetic.csv',$csv)])->assertRedirect();

        return basename($response->headers->get('Location'));
    }
}
