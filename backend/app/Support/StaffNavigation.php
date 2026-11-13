<?php

namespace App\Support;

use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use App\Modules\Shared\Access;
use Illuminate\Http\Request;

final class StaffNavigation
{
    public static function groups(User $user): array
    {
        $sections = WorkspaceController::sections();
        $workspace = fn (string $key, ?string $label = null) => [
            'label' => $label ?? $sections[$key][0],
            'href' => '/workspace/'.$key,
            'roles' => $sections[$key][1],
            'active' => ['workspace/'.$key, 'workspace/'.$key.'/*'],
        ];
        $link = fn (string $label, string $href, array $roles, array $active) => compact('label', 'href', 'roles', 'active');
        $item = fn (string $key, string $label, string $icon, array $children) => compact('key', 'label', 'icon', 'children');
        $flow = function (string $section, string $key) use ($workspace) {
            $entry = $workspace($section, WorkspaceFlow::definitions($section)[$key]['title']);
            $entry['href'] .= '?flow='.$key;
            $entry['flow'] = $key;
            $entry['defaultFlow'] = array_key_first(WorkspaceFlow::definitions($section)) === $key;
            if ($section === 'catalog' && in_array($key, ['airports', 'infrastructure', 'routes'], true)) {
                $entry['aliases'] = ['workspace/catalog/'.$key.'/*'];
            }
            if ($section === 'scheduling' && $key === 'planning') {
                $entry['aliases'] = ['workspace/planning', 'workspace/planning/*'];
                $entry['roles'][] = 'data_admin';
            }
            if($section==='scheduling'&&$key==='monitoring')$entry['aliases']=['workspace/operations/flights','workspace/operations/flights/*'];

            return $entry;
        };
        $withRelated = function (array $entry, array $related, array $aliases = []) use ($user) {
            if (! Access::allows($user, ...$entry['roles'])) {
                foreach ($related as $step) {
                    if (Access::allows($user, ...$step['roles'])) {
                        $entry['href'] = $step['href'];
                        break;
                    }
                }
            }
            $entry['roles'] = array_values(array_unique(array_merge($entry['roles'], ...array_column($related, 'roles'))));

            return $entry + ['related' => $related, 'aliases' => $aliases];
        };

        $groups = [
            ['label' => 'Quản lý khai thác', 'items' => [
                $item('airports', 'Quản lý sân bay', 'airport', [
                    $flow('catalog', 'airports') + ['statusDirectory' => true],
                    array_replace($flow('catalog', 'infrastructure'), ['label' => 'Cơ sở hạ tầng sân bay']),
                    $workspace('resources', 'Lịch sử dụng hạ tầng'),
                ]),
                $item('flights', 'Quản lý chuyến bay', 'calendar', [
                    $flow('catalog', 'routes'),
                    $flow('scheduling', 'planning'),
                ]),
                $item('operations', 'Điều hành chuyến bay', 'departure', [
                    $flow('scheduling', 'monitoring'),
                    $withRelated($workspace('disruptions', 'Gián đoạn và phục hồi'), [
                        $workspace('disruptions', 'Xử lý chậm / hủy chuyến'),
                        $workspace('recovery', 'Đổi tàu & tổ bay'),
                        $workspace('passenger-recovery', 'Hỗ trợ khách bị gián đoạn'),
                    ], ['workspace/recovery*', 'workspace/passenger-recovery*']),
                ]),
                $item('aircraft', 'Quản lý máy bay', 'plane', [
                    $withRelated($workspace('fleet', 'Đội máy bay'), [
                        $workspace('fleet', 'Hồ sơ đội bay'),
                        $link('Cấu hình khoang khách', '/workspace/fleet-cabin', ['fleet_manager'], ['workspace/fleet-cabin*']),
                    ], ['workspace/fleet-cabin*']),
                    $workspace('maintenance', 'Bảo dưỡng máy bay'),
                ]),
                $item('crew', 'Nhân sự phi hành đoàn', 'users', [
                    $flow('crew', 'profiles'),
                    $flow('crew', 'assignments'),
                ]),
                $item('ground', 'Dịch vụ mặt đất', 'truck', [
                    $flow('ground', 'tasks') + ['aliases' => ['workspace/infrastructure-reference/ground*']],
                    $flow('ground', 'tracking'),
                ]),
            ]],
            ['label' => 'Hành khách và vé', 'items' => [
                $item('passengers', 'Quản lý hành khách', 'passenger', [
                    $link('Hồ sơ hành khách', '/staff/passengers', ['airport_agent'], ['staff/passengers*']),
                    $withRelated($workspace('boarding-control', 'Check-in và lên máy bay'), [
                        $link('Làm thủ tục hành khách', '/staff/passengers', ['airport_agent'], ['staff/passengers*']),
                        $workspace('boarding-control', 'Cửa sổ check-in & danh sách'),
                        $link('Quét thẻ lên máy bay', '/staff#boarding-console', ['airport_agent'], []),
                    ]),
                    $workspace('baggage', 'Quản lý hành lý') + ['aliases' => ['workspace/infrastructure-reference/baggage*']],
                    $withRelated($workspace('customer', 'Chăm sóc khách hàng'), [
                        $workspace('customer', 'Yêu cầu & trao đổi'),
                        $workspace('support-desk', 'Phân công & hạn hỗ trợ'),
                        $workspace('passenger-revisions', 'Duyệt thông tin hành khách'),
                    ], ['support/*', 'workspace/support-desk*', 'workspace/passenger-revisions*']),
                ]),
                $item('tickets', 'Vé và thanh toán', 'ticket', [
                    $link('Đặt chỗ và vé', '/modules/m2?flow=bookings', ['airport_agent', 'customer_service', 'operations'], ['modules/m2']),
                    $withRelated($workspace('fare', 'Giá vé và tồn chỗ'), [
                        $workspace('fare', 'Giá vé & hạn mức chỗ'),
                        $workspace('fare-rules', 'Điều kiện vé & hành lý'),
                    ], ['workspace/fare-rules*']),
                ]),
            ]],
            ['label' => 'Giám sát và kiểm soát', 'items' => [
                ['key' => 'alerts', 'icon' => 'alert'] + $withRelated($link('Cảnh báo thông minh', '/modules/m3?flow=alerts', ['analyst', 'data_admin', 'operations', 'ops_manager', 'maintenance', 'integration_admin'], ['modules/m3']), [
                    $link('Cảnh báo & đánh giá rủi ro', '/modules/m3?flow=alerts', ['analyst', 'data_admin', 'operations', 'ops_manager', 'maintenance', 'integration_admin'], ['modules/m3']),
                    $workspace('iot', 'Thiết bị & dữ liệu cảm biến'),
                ], ['workspace/iot*']),
                ['key' => 'reports', 'label' => 'Phân tích và báo cáo', 'icon' => 'chart'] + $workspace('analytics', 'Phân tích và báo cáo'),
                $item('finance', 'Tài chính và đối soát', 'wallet', [
                    $withRelated($flow('finance', 'transactions'), [
                        $flow('finance', 'transactions'),
                        $workspace('financial-adjustments', 'Điều chỉnh tài chính'),
                    ], ['workspace/financial-adjustments*']),
                    $withRelated($flow('finance', 'reconciliation'), [
                        $flow('finance', 'reconciliation'),
                        $workspace('bank-statements', 'Nhập sao kê & đối chiếu'),
                        $workspace('revenue', 'Báo cáo doanh thu'),
                    ], ['workspace/bank-statements*', 'workspace/revenue*', 'workspace/reports/*']),
                ]),
            ]],
            ['label' => 'Cá nhân', 'items' => [
                $item('account', 'Tài khoản và hồ sơ cá nhân', 'account', [
                    $link('Hồ sơ cá nhân', '/profile', Access::ROLES, ['profile*']),
                    $workspace('notifications'),
                    $link('Quản lý tài khoản', '/admin/users', ['admin'], ['admin/users*']),
                    $link('Email & thanh toán', '/admin/connections', ['admin'], ['admin/connections*']),
                    $workspace('audit'),
                    $workspace('integrations'),
                ]),
            ]],
        ];

        // Match the same role checks used by the destination pages.
        foreach ($groups as &$group) {
            $visible = [];
            foreach ($group['items'] as $entry) {
                if (isset($entry['children'])) {
                    $entry['children'] = array_values(array_filter($entry['children'], fn ($child) => Access::allows($user, ...$child['roles']) || (!empty($child['statusDirectory']) && (AirportStatusAuthority::scopes($user) || (in_array($user->role, Access::SCOPED, true) && $user->airport_code)))));
                    if (! $entry['children']) {
                        continue;
                    }
                } elseif (! Access::allows($user, ...$entry['roles'])) {
                    continue;
                }
                if (isset($entry['related'])) {
                    $entry['related'] = array_values(array_filter($entry['related'], fn ($related) => Access::allows($user, ...$related['roles'])));
                }
                $visible[] = $entry;
            }
            $group['items'] = $visible;
        }
        unset($group);

        return array_values(array_filter($groups, fn ($group) => $group['items']));
    }

    public static function isActive(array $entry, Request $request): bool
    {
        if (! empty($entry['aliases']) && $request->is(...$entry['aliases'])) {
            return true;
        }
        if (isset($entry['flow']) && ! $request->is($entry['active'][0])) {
            return false;
        }
        if (! $request->is(...$entry['active'])) {
            return false;
        }

        return ! isset($entry['flow']) || $request->query('flow') === $entry['flow']
            || (! $request->has('flow') && ! empty($entry['defaultFlow']));
    }

    public static function context(User $user, Request $request): array
    {
        foreach (self::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $entry) {
                    if (self::isActive($entry, $request)) {
                        $related = array_values(array_filter($entry['related'] ?? [], fn ($link) => Access::allows($user, ...$link['roles'])));

                        return ['label' => $entry['label'], 'links' => $related];
                    }
                }
            }
        }

        return [];
    }
}
