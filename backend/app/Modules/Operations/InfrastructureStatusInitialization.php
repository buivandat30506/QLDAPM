<?php

namespace App\Modules\Operations;

use App\Modules\Shared\Audit;
use Illuminate\Support\Facades\DB;

class InfrastructureStatusInitialization
{
    public function run(bool $apply = false): array
    {
        abort_unless(app()->environment(['local', 'testing']), 403);

        return DB::connection('ops')->transaction(function () use ($apply) {
            $db = DB::connection('ops');
            $db->table('airports')->orderBy('code')->lockForUpdate()->get();
            $rows = $db->table('airport_infrastructure')->where('active', true)->whereNull('technical_state')->orderBy('id')->lockForUpdate()->get();
            $result = ['apply' => $apply, 'eligible' => $rows->count(), 'updated' => 0];
            if (! $apply) {
                return $result;
            }
            $service = app(InfrastructureService::class);
            foreach ($rows as $row) {
                $before = $service->snapshot($row->id);
                $db->table('airport_infrastructure')->where('id', $row->id)->update(['technical_state' => 'NORMAL', 'version' => $row->version + 1, 'updated_at' => now()]);
                Audit::record('infrastructure.status.initialized', (string) $row->id, json_encode($before, JSON_THROW_ON_ERROR), json_encode($service->snapshot($row->id), JSON_THROW_ON_ERROR), null, 'ops');
                $result['updated']++;
            }

            return $result;
        }, 3);
    }
}
