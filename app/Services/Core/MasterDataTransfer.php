<?php

namespace App\Services\Core;

use App\Shared\CsvSyntax;
use App\Shared\Security\SensitiveData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterDataTransfer
{
    public function __construct(private MasterDataQuality $quality, private MasterDataService $masters) {}

    public function preview(UploadedFile $file): object
    {
        Gate::authorize('master.write');
        if (strtolower($file->getClientOriginalExtension()) !== 'csv' || $file->getSize() > 1024 * 1024) {
            throw ValidationException::withMessages(['file' => ['Chọn tệp CSV UTF-8, tối đa 1 MiB.']]);
        }
        $raw = file_get_contents($file->getRealPath());
        $fileHash = hash('sha256', $raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8') || str_contains($raw, "\0")) {
            throw ValidationException::withMessages(['file' => ['Tệp phải là văn bản UTF-8 hợp lệ.']]);
        }
        if (! CsvSyntax::valid($raw)) {
            throw ValidationException::withMessages(['file' => ['Dấu nháy CSV không hợp lệ hoặc chưa đóng. Dùng tệp mẫu và escape nháy kép bằng hai dấu nháy.']]);
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $raw);
        rewind($stream);
        try {
            if (fgetcsv($stream, 0, ',', '"', '') !== ['category', 'code', 'payload']) {
                throw ValidationException::withMessages(['file' => ['Header bắt buộc: category,code,payload. Xem tệp mẫu.']]);
            }
            $rows = [];
            $seen = [];
            while (($fields = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($fields === [null]) {
                    continue;
                }
                if (count($rows) >= 1000) {
                    throw ValidationException::withMessages(['file' => ['Tối đa 1.000 bản ghi mỗi tệp.']]);
                }
                $errors = [];
                if (count($fields) !== 3) {
                    $errors['row'][] = 'Mỗi bản ghi phải có đúng ba cột.';
                }
                try {
                    $payload = json_decode($fields[2] ?? '', true, 16, JSON_THROW_ON_ERROR);
                    if (! is_array($payload) || array_is_list($payload)) {
                        throw new \JsonException('Object required');
                    }
                } catch (\JsonException) {
                    $payload = [];
                    $errors['payload'][] = 'Payload phải là JSON object hợp lệ.';
                }
                $input = $this->quality->normalize(['category' => $fields[0] ?? '', 'code' => $fields[1] ?? '', 'payload' => $payload]);
                $errors = array_merge_recursive($errors, $this->quality->errors($input));
                $key = $input['category'].'/'.$input['code'];
                if (isset($seen[$key])) {
                    $errors['code'][] = 'Mã trùng với bản ghi trước trong cùng tệp.';
                }
                $seen[$key] = true;
                $rows[] = ['line' => count($rows) + 2, 'input' => $input, 'errors' => $errors];
            }
            if (! $rows) {
                throw ValidationException::withMessages(['file' => ['Tệp chưa có bản ghi.']]);
            }
        } finally {
            fclose($stream);
        }

        return DB::transaction(function () use ($rows, $file, $fileHash) {
            $id = (string) Str::uuid();
            $errorCount = count(array_filter($rows, fn ($r) => (bool) $r['errors']));
            DB::table('master_import_batches')->insert(['id' => $id, 'created_by' => auth()->id(),
                'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 180), 'sha256' => $fileHash,
                'status' => $errorCount ? 'INVALID' : 'READY', 'row_count' => count($rows), 'error_count' => $errorCount,
                'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            foreach ($rows as $row) {
                DB::table('master_import_rows')->insert(['batch_id' => $id, 'line' => $row['line'],
                    'input' => json_encode(SensitiveData::redact($row['input']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'errors' => json_encode($row['errors'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            }
            AuditService::record('master_import_batches', $id, 'IMPORT_PREVIEW', null, ['row_count' => count($rows), 'error_count' => $errorCount]);

            return $this->batch($id);
        });
    }

    public function batch(string $id): object
    {
        Gate::authorize('master.read');
        $batch = DB::table('master_import_batches')->find($id);
        abort_unless($batch, 404);

        return $batch;
    }

    public function commit(string $id): object
    {
        Gate::authorize('master.write');
        // Return validation errors AFTER committing the failed revalidation diagnostics.
        $result = DB::transaction(function () use ($id) {
            $this->masters->lock();
            $batch = DB::table('master_import_batches')->lockForUpdate()->find($id);
            abort_unless($batch, 404);
            if ($batch->status === 'COMMITTED') {
                return $batch;
            }
            if ($batch->status !== 'READY') {
                throw ValidationException::withMessages(['batch' => ['Tệp chưa đạt chất lượng. Sửa tệp rồi kiểm tra lại.']]);
            }
            $rows = DB::table('master_import_rows')->where('batch_id', $id)->orderBy('line')->get();
            $failed = 0;
            foreach ($rows as $row) {
                $errors = $this->quality->errors(json_decode($row->input, true));
                if ($errors) {
                    $failed++;
                }
                DB::table('master_import_rows')->where('id', $row->id)->update(['errors' => json_encode($errors, JSON_THROW_ON_ERROR)]);
            }
            if ($failed) {
                DB::table('master_import_batches')->where('id', $id)->update(['status' => 'INVALID', 'error_count' => $failed, 'updated_at' => now('UTC')]);
                AuditService::record('master_import_batches', $id, 'IMPORT_REVALIDATION_FAILED', null, ['error_count' => $failed], outcome: 'REJECTED');

                return null;
            }
            foreach ($rows as $row) {
                $record = $this->masters->save(json_decode($row->input, true));
                DB::table('master_import_rows')->where('id', $row->id)->update(['record_id' => $record->id]);
            }
            DB::table('master_import_batches')->where('id', $id)->update(['status' => 'COMMITTED', 'committed_at' => now('UTC'), 'updated_at' => now('UTC')]);
            AuditService::record('master_import_batches', $id, 'IMPORT_COMMITTED', null, ['row_count' => $rows->count()]);

            return $this->batch($id);
        });
        if ($result === null) {
            throw ValidationException::withMessages(['batch' => ['Dữ liệu thay đổi sau khi kiểm tra. Không bản ghi nào được nhập; xem lỗi mới trong lô.']]);
        }

        return $result;
    }

    public function export(array $filters)
    {
        Gate::authorize('master.export');
        $query = $this->masters->query($filters);
        AuditService::record('master_data_records', 'export', 'MASTER_EXPORTED', null, ['filters' => $filters]);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['category', 'code', 'name', 'status', 'version', 'payload'], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $payload = json_decode($row->payload, true);
                fputcsv($stream, array_map(self::safeCell(...), [$row->category, $row->code, $payload['name'] ?? '', $row->status, $row->version, $row->payload]), ',', '"', '');
            }
            fclose($stream);
        }, 'master-data.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x{FEFF}]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
