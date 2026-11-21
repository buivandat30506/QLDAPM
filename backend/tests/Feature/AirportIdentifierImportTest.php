<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\MasterDataService;
use Database\Seeders\AirportIdentifierSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AirportIdentifierImportTest extends TestCase
{
    public function test_import_preserves_pending_approval_but_manual_identifier_changes_still_conflict(): void
    {
        $author = User::factory()->create(['role' => 'data_admin']);
        $reviewer = User::factory()->create(['role' => 'data_admin']);
        $master = app(MasterDataService::class);
        $changes = ['name' => 'Updated airport', 'city' => 'Test city', 'timezone' => 'Asia/Ho_Chi_Minh'];
        $pending = [];
        foreach (['HAN', 'SGN'] as $code) {
            $pending[$code] = $master->propose($author, ['entity' => 'airports', 'record_id' => $code, 'changes' => $changes, 'reason' => 'Update general airport profile'])->id;
        }
        $original = DB::connection('ops')->table('master_changes')->where('id', $pending['HAN'])->value('before_data');
        (new AirportIdentifierSeeder)->synchronize(true);
        $master->approve($reviewer, $pending['HAN']);
        $this->assertSame($original, DB::connection('ops')->table('master_changes')->where('id', $pending['HAN'])->value('before_data'));
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'icao' => 'VVNB', 'country_code' => 'VN', 'name' => 'Updated airport'], 'ops');
        DB::connection('ops')->table('airports')->where('code', 'SGN')->update(['country_code' => 'US']);
        $this->actingAs($reviewer)->postJson('/api/v1/actions/master_approve', ['change_id' => $pending['SGN']])->assertConflict();
    }

    public function test_import_fills_missing_identifiers_once_and_preserves_other_airport_data(): void
    {
        $db = DB::connection('ops');
        $before = $db->table('airports')->orderBy('code')->get()->map(fn ($row) => (array) $row)->all();
        $importer = new AirportIdentifierSeeder;
        $this->assertSame(3, $importer->synchronize()['updated_airports']);
        $this->assertNull($db->table('airports')->where('code', 'HAN')->value('icao'));
        $this->assertSame(3, $importer->synchronize(true)['updated_airports']);
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'icao' => 'VVNB', 'country_code' => 'VN'], 'ops');
        foreach ($before as $row) {
            $after = (array) $db->table('airports')->where('code', $row['code'])->first();
            unset($row['icao'], $row['country_code'], $after['icao'], $after['country_code']);
            $this->assertSame($row, $after);
        }
        $audits = $db->table('audit_logs')->count();
        $this->assertSame(0, $importer->synchronize(true)['updated_airports']);
        $this->assertSame($audits, $db->table('audit_logs')->count());
    }

    public function test_manual_values_and_unknown_airports_are_preserved(): void
    {
        $db = DB::connection('ops');
        $db->table('airports')->where('code', 'HAN')->update(['icao' => 'TEST', 'country_code' => 'US']);
        $db->table('airports')->insert(['code' => 'ZZZ', 'name' => 'Unknown reference', 'city' => 'Test', 'timezone' => 'UTC']);
        $result = (new AirportIdentifierSeeder)->synchronize(true);
        $this->assertContains('HAN.icao', $result['preserved_conflicts']);
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'icao' => 'TEST', 'country_code' => 'US'], 'ops');
        $this->assertDatabaseHas('airports', ['code' => 'ZZZ', 'icao' => null, 'country_code' => null], 'ops');
    }
}
