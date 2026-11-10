<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AirportHistory
{
    private const SYSTEM_ACTIONS = ['airport.operational_status.simulated','airport.operational_status.simulation.cleared'];
    private const ACTIONS = ['airport.created','airport.updated','airport.identifiers.imported','airport.operational_status.updated', ...self::SYSTEM_ACTIONS];

    public static function pages(string $airport, bool $canEdit): array
    {
        $db = DB::connection('ops');
        $events = $db->table('audit_logs')->where('object_id',$airport)->whereIn('action',self::ACTIONS);
        $main = (clone $events)->where(fn($q)=>$q->whereNotIn('action',self::SYSTEM_ACTIONS)->orWhereNotNull('actor_id'))
            ->selectRaw("'event' AS kind, id, action, 'RECORDED' AS status, before_state AS before_data, after_state AS after_data, NULL AS reason, actor_id AS created_by, NULL AS approved_by, created_at AS occurred_at");
        if ($canEdit) {
            $proposals = $db->table('master_changes')->where('entity','airports')->where('record_id',$airport)
                ->selectRaw("'proposal' AS kind, id, 'airport.profile.proposed' AS action, status, before_data, after_data, reason, created_by, approved_by, CASE WHEN status = 'APPROVED' THEN updated_at ELSE created_at END AS occurred_at");
            $main->unionAll($proposals);
        }
        return [
            'history'=>$db->query()->fromSub($main,'airport_history')->orderByDesc('occurred_at')->orderByDesc('id')->orderBy('kind')->paginate(5,['*'],'history_page')->withQueryString()->fragment('airport-history'),
            'systemHistory'=>(clone $events)->whereIn('action',self::SYSTEM_ACTIONS)->whereNull('actor_id')->orderByDesc('created_at')->orderByDesc('id')->paginate(5,['*'],'system_page')->withQueryString()->fragment('airport-history'),
        ];
    }

    public static function title(object $entry): string
    {
        if (($entry->kind ?? null) === 'proposal') {
            return $entry->status === 'APPROVED' ? 'Cập nhật hồ sơ' : 'Đề nghị sửa hồ sơ';
        }
        return ['airport.created'=>'Tạo hồ sơ sân bay','airport.updated'=>'Cập nhật hồ sơ','airport.identifiers.imported'=>'Bổ sung mã ICAO và quốc gia','airport.operational_status.updated'=>'Cập nhật trạng thái vận hành','airport.operational_status.simulated'=>'Khởi tạo trạng thái sân bay','airport.operational_status.simulation.cleared'=>'Đặt lại trạng thái sân bay'][$entry->action] ?? 'Cập nhật dữ liệu';
    }
}
