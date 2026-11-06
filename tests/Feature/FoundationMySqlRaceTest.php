<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Core\MasterDataService;
use App\Services\Core\MasterDataTransfer;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationMySqlRaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('M01 concurrency requires independent MySQL connections.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
        $this->seed();
        $this->actingAs(User::where('username', 'data_demo')->firstOrFail());
    }

    public function test_two_imports_with_the_same_identifier_commit_only_one_complete_batch(): void
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['category', 'code', 'payload'], ',', '"', '');
        foreach (['SHARED_RACE', 'OTHER_RACE'] as $code) {
            fputcsv($stream, ['CABIN', $code, '{"name":"Race fixture"}'], ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $ids[] = app(MasterDataTransfer::class)->preview(UploadedFile::fake()->createWithContent('race.csv', $csv))->id;
        }
        $this->contend('import', $ids, 'IT-MASTER-IMPORT-RACE-01', 'import-race.json');
        $this->assertSame(2, DB::table('master_data_records')->whereIn('code', ['SHARED_RACE', 'OTHER_RACE'])->count());
        $this->assertSame(1, DB::table('master_import_batches')->whereIn('id', $ids)->where('status', 'COMMITTED')->count());
        $this->assertSame(1, DB::table('master_import_batches')->whereIn('id', $ids)->where('status', 'INVALID')->count());
        $this->assertSame(2, DB::table('master_import_rows')->whereIn('batch_id', $ids)->whereNotNull('record_id')->count());
    }

    public function test_two_publishers_create_exactly_one_version_with_one_responsible_actor(): void
    {
        $service = app(MasterDataService::class);
        $id = $service->save(['category' => 'CABIN', 'code' => 'PUBLISH_RACE', 'payload' => ['name' => 'Race fixture']])->id;
        foreach (['submit', 'approve'] as $action) {
            $service->transition($id, $action);
        }
        $this->contend('publish', [(string) $id, (string) $id], 'IT-MASTER-PUBLISH-RACE-01', 'master-publish-race.json');
        $this->assertSame(1, DB::table('entity_versions')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->count());
        $this->assertDatabaseHas('master_data_records', ['id' => $id, 'status' => 'PUBLISHED', 'version' => 1, 'published_by' => auth()->id()]);
    }

    private function contend(string $mode, array $ids, string $testId, string $filename): void
    {
        $workers = [];
        DB::beginTransaction();
        DB::table('domain_locks')->where('name', 'master-data')->lockForUpdate()->firstOrFail();
        try {
            foreach ($ids as $id) {
                $process = proc_open([PHP_BINARY, base_path('scripts/foundation-race-worker.php'), $mode, $id, auth()->id()],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($ready['ready']);
                $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                $workers[] = compact('process', 'pipes', 'ready');
                fclose($pipes[0]);
            }
            $connections = array_column(array_column($workers, 'ready'), 'connection_id');
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?, ?)', $connections)->n;
                if ($waiting === 2) {
                    break;
                } usleep(20000);
            } while (microtime(true) < $deadline);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $result = json_decode(stream_get_contents($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $this->assertSame(0, proc_close($worker['process']), $stderr.json_encode($result));
                $results[] = $result;
            }
            $this->assertSame(2, $waiting, 'Both independent sessions must wait on the master-data mutex.');
            $this->assertEqualsCanonicalizing(['committed', 'rejected'], array_column($results, 'result'));
            $evidence = getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18-g2');
            file_put_contents($evidence.'/'.$filename, json_encode(['test_id' => $testId, 'engine' => DB::selectOne('SELECT VERSION() AS v')->v,
                'database' => DB::connection()->getDatabaseName(), 'connections' => $connections, 'observed_lock_waiters' => $waiting, 'results' => $results, 'executed_at_utc' => gmdate('c')], JSON_PRETTY_PRINT));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
            }
        }
    }
}
