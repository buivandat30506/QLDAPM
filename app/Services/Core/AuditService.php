<?php

namespace App\Services\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Shared\Security\SensitiveData;

class AuditService
{
    public static function record(string $type, string|int $id, string $action, mixed $before = null, mixed $after = null, ?string $reason = null, string $outcome = 'SUCCESS', ?string $actorId = null): void
    {
        DB::table('audit_logs')->insert([
            'entity_type' => $type, 'entity_id' => (string) $id, 'action' => $action,
            'actor_id' => $actorId ?? Auth::id(), 'before_data' => $before === null ? null : json_encode(SensitiveData::redact($before), JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode(SensitiveData::redact($after), JSON_THROW_ON_ERROR),
            'correlation_id' => app()->bound('request') ? request()->attributes->get('correlation_id') : null,
            'outcome' => $outcome, 'reason' => $reason, 'created_at' => now('UTC'),
        ]);
    }

    public static function observe(Model $model): void
    {
        self::record($model->getTable(), $model->getKey(), $model->wasRecentlyCreated ? 'CREATED' : 'UPDATED',
            $model->wasRecentlyCreated ? null : $model->getRawOriginal(), $model->getAttributes());
    }

    public static function version(string $type, string|int $id, int $version, mixed $snapshot): void
    {
        DB::table('entity_versions')->insert([
            'entity_type' => $type, 'entity_id' => (string) $id, 'version' => $version,
            'snapshot' => json_encode($snapshot), 'created_at' => now('UTC'),
        ]);
    }
}
