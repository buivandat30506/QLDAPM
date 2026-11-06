<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    public function __construct(private MasterDataQuality $quality) {}

    public function lock(): void
    {
        DB::table('domain_locks')->where('name', 'master-data')->lockForUpdate()->firstOrFail();
    }

    public function query(array $filters = [])
    {
        Validator::make($filters, ['search' => 'nullable|string|max:100', 'category' => 'nullable|in:CABIN,SERVICE,CURRENCY',
            'status' => 'nullable|in:DRAFT,PENDING,APPROVED,PUBLISHED', 'per_page' => 'nullable|integer|between:1,100',
            'sort' => 'nullable|in:code,category,updated_at', 'direction' => 'nullable|in:asc,desc'])->validate();

        return DB::table('master_data_records')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$s.'%')->orWhere('payload->name', 'like', '%'.$s.'%')))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($filters['sort'] ?? 'updated_at', $filters['direction'] ?? 'desc')->orderBy('id');
    }

    public function save(array $data, ?int $id = null): object
    {
        return DB::transaction(function () use ($data, $id) {
            // Mutex before consistent reads: avoid stale MySQL REPEATABLE READ snapshots.
            $this->lock();
            $before = $id ? DB::table('master_data_records')->lockForUpdate()->find($id) : null;
            abort_if($id && ! $before, 404);
            if ($before && $before->status !== 'DRAFT') {
                throw ValidationException::withMessages(['status' => ['Chỉ sửa bản nháp. Tạo bản sửa đổi từ dữ liệu đã công bố.']]);
            }
            if ($id) {
                Validator::make($data, ['expected_revision' => 'required|integer|min:0'])->validate();
                if ((int) $data['expected_revision'] !== $before->revision) {
                    abort(409, 'Dữ liệu đã thay đổi. Tải lại trước khi lưu.');
                }
            }
            $input = $this->quality->normalize($data);
            $errors = $this->quality->errors($input, $id);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            if ($before && ($input['category'] !== $before->category || $input['code'] !== $before->code)) {
                throw ValidationException::withMessages(['code' => ['Không đổi định danh; tạo dữ liệu mới khi cần mã khác.']]);
            }
            $values = ['category' => $input['category'], 'code' => $input['code'],
                'payload' => json_encode($input['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'revision' => ($before?->revision ?? -1) + 1, 'updated_at' => now('UTC')];
            if ($id) {
                DB::table('master_data_records')->where('id', $id)->update($values);
            } else {
                $id = DB::table('master_data_records')->insertGetId($values + ['status' => 'DRAFT', 'version' => 0, 'created_at' => now('UTC')]);
            }
            $after = DB::table('master_data_records')->find($id);
            AuditService::record('master_data_records', $id, $before ? 'UPDATED' : 'CREATED', $before, $after);

            return $after;
        });
    }

    public function transition(int $id, string $action, array $data = []): object
    {
        return DB::transaction(function () use ($id, $action, $data) {
            $this->lock();
            $record = DB::table('master_data_records')->lockForUpdate()->find($id);
            abort_unless($record, 404);
            $steps = ['submit' => ['DRAFT', 'PENDING'], 'approve' => ['PENDING', 'APPROVED'], 'publish' => ['APPROVED', 'PUBLISHED'],
                'revise' => ['PUBLISHED', 'DRAFT'], 'reject' => [['PENDING', 'APPROVED'], 'DRAFT']];
            if (! isset($steps[$action]) || ! in_array($record->status, (array) $steps[$action][0], true)) {
                throw ValidationException::withMessages(['status' => ['Thao tác không hợp lệ với trạng thái hiện tại.']]);
            }
            if (array_key_exists('expected_revision', $data) && (string) $data['expected_revision'] !== (string) $record->revision) {
                abort(409, 'Dữ liệu đã thay đổi. Tải lại trước khi thao tác.');
            }
            if (in_array($action, ['revise', 'reject'])) {
                Validator::make($data, ['reason' => 'required|string|min:5|max:500'])->validate();
            } else {
                $errors = $this->quality->errors(['category' => $record->category, 'code' => $record->code, 'payload' => json_decode($record->payload, true)], $id);
                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
            }
            $version = $record->version + ($action === 'publish' ? 1 : 0);
            $values = ['status' => $steps[$action][1], 'version' => $version, 'revision' => $record->revision + 1, 'updated_at' => now('UTC')];
            $step = ['submit' => 'submitted', 'approve' => 'approved', 'publish' => 'published'][$action] ?? null;
            if ($step) {
                $values[$step.'_by'] = auth()->id();
                $values[$step.'_at'] = now('UTC');
            }
            if (in_array($action, ['revise', 'reject'])) {
                $values += ['submitted_by' => null, 'submitted_at' => null, 'approved_by' => null, 'approved_at' => null];
            }
            DB::table('master_data_records')->where('id', $id)->update($values);
            $after = DB::table('master_data_records')->find($id);
            if ($action === 'publish') {
                AuditService::version('master_data_records', $id, $version, $after);
            }
            AuditService::record('master_data_records', $id, strtoupper($action), $record, $after, $data['reason'] ?? null);

            return $after;
        });
    }

    public function published(int $id): object
    {
        $snapshot = DB::table('entity_versions')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->orderByDesc('version')->first();
        abort_unless($snapshot, 404);

        return json_decode($snapshot->snapshot, false, 512, JSON_THROW_ON_ERROR);
    }
}
