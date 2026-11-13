<?php

namespace App\Support;

use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Website scope only. Domain services, outbox consumers and payment callbacks stay intact. */
final class ReleaseScope
{
    public const ACTIVE_SECTIONS = ['catalog', 'scheduling', 'resources', 'disruptions', 'recovery', 'passenger-recovery', 'notifications', 'audit', 'integrations'];

    public const ACTIONS = ['airport', 'route', 'resource', 'airport_update', 'airport_area', 'master_propose', 'master_approve', 'season_rule', 'schedule', 'season', 'submit', 'submit_batch', 'publish', 'publish_batch', 'flight_state', 'actual_time', 'assign_crew', 'ground_state', 'allocate', 'unallocate', 'resource_propose', 'resource_decide', 'disruption', 'approve_disruption', 'read_notice', 'read_all_notices'];

    public static function page(Request $r): ?array
    {
        $section = $r->route('section');
        if ($section && isset(WorkspaceController::sections()[$section]) && ! in_array($section, self::ACTIVE_SECTIONS, true)) {
            [$title, $roles] = WorkspaceController::sections()[$section];
            $flow = $r->query('flow');
            if (is_string($flow)) {
                $title = WorkspaceFlow::definitions($section)[$flow]['title'] ?? $title;
            }

            return [$title, $roles];
        }
        if ($r->is('modules/*', 'api/v1/modules/*')) {
            return match ($r->route('module')) {
                'm1' => ['Quản lý cốt lõi', ['admin', 'data_admin', 'operations', 'ops_manager', 'fleet_manager', 'maintenance', 'crew_scheduler', 'ground_agent', 'integration_admin']],
                'm2' => ['Đặt chỗ và vé', ['admin', 'passenger', 'airport_agent', 'customer_service', 'operations']],
                'm3' => ['Cảnh báo thông minh', ['admin', 'analyst', 'data_admin', 'operations', 'ops_manager', 'maintenance', 'integration_admin']],
                default => null,
            };
        }
        if ($r->is('workspace/fleet-cabin')) {
            return ['Cấu hình khoang khách', ['fleet_manager']];
        }
        if ($r->is('staff/passengers')) {
            return ['Hồ sơ hành khách', ['airport_agent']];
        }
        if ($r->is('workspace/infrastructure-reference/ground', 'api/v1/infrastructure-reference/ground')) {
            return ['Dịch vụ mặt đất', ['ground_agent', 'operations', 'data_admin']];
        }
        if ($r->is('workspace/infrastructure-reference/baggage', 'api/v1/infrastructure-reference/baggage')) {
            return ['Quản lý hành lý', ['airport_agent', 'data_admin']];
        }
        if ($r->is('workspace/reports/*')) {
            return ['Đối soát doanh thu', ['finance']];
        }
        if ($r->is('workspace/master/export/aircraft')) {
            return ['Đội máy bay', ['fleet_manager']];
        }
        if ($r->is('workspace/master/export/crew_members')) {
            return ['Hồ sơ phi hành đoàn', ['crew_scheduler']];
        }
        if ($r->is('workspace/passenger-revisions/*')) {
            return ['Duyệt sửa thông tin khách', ['customer_service']];
        }
        if ($r->is('baggage/*', 'api/v1/baggage/*')) {
            return ['Quản lý hành lý', ['airport_agent', 'passenger']];
        }
        if ($r->is('support/*', 'api/v1/support-cases/*')) {
            return ['Yêu cầu hỗ trợ', ['customer_service', 'passenger']];
        }
        if ($r->is('manifests/*', 'api/v1/manifests/*')) {
            return ['Danh sách lên tàu', ['airport_agent', 'operations', 'ops_manager']];
        }

        return null;
    }

    public static function allowsAction(Request $r, string $action): bool
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return false;
        }
        // The generic master-data endpoint must not become a back door to suspended modules.
        if (in_array($action, ['master_propose', 'master_approve'], true)) {
            $entity = $action === 'master_propose' ? $r->input('entity')
                : DB::connection('ops')->table('master_changes')->where('id', $r->input('change_id'))->value('entity');

            return in_array($entity, ['airports', 'airport_resources', 'airport_infrastructure', 'routes'], true);
        }

        return true;
    }
}
