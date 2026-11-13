<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Shared\Access;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StaffDashboard
{
    public function data(User $u): array
    {
        $tiles = [];
        foreach ([
            ['/workspace/catalog?flow=airports', 'Quản lý sân bay', 'Hồ sơ sân bay, trạng thái và cơ sở hạ tầng.', ['data_admin']],
            ['/workspace/catalog?flow=routes', 'Đường bay', 'Danh mục đường bay và các thay đổi chờ duyệt.', ['data_admin']],
            ['/workspace/planning', 'Đường bay và lịch bay', 'Kế hoạch định kỳ, phát sinh, kiểm tra và công bố lịch.', ['data_admin', 'operations', 'ops_manager']],
            ['/workspace/operations/flights', 'Điều hành chuyến bay', 'Theo dõi khai thác, cập nhật trạng thái và nhật ký.', ['operations', 'ops_manager']],
            ['/workspace/disruptions', 'Gián đoạn và phục hồi', 'Đề nghị, kiểm tra và phê duyệt thay đổi khai thác.', ['operations', 'ops_manager']],
            ['/plan', 'Đặt chuyến đi', 'Tìm chuyến và thực hiện đặt chỗ.', Access::ROLES],
        ] as [$url, $title, $description, $roles]) {
            if (Access::allows($u, ...$roles)) {
                $tiles[] = compact('url', 'title', 'description') + ['allowed' => true];
            }
        }
        $ops = DB::connection('ops');
        if (Access::allows($u, 'crew_scheduler')) {
            $tiles[] = ['url' => '/workspace/planning/crew-assignments', 'title' => 'Tổ bay cho lịch chuyến', 'description' => 'Phân công phục vụ công bố lịch M02.', 'allowed' => true];
        }
        if (Access::allows($u, 'ground_agent')) {
            $tiles[] = ['url' => '/workspace/operations/ground-tasks', 'title' => 'Nhiệm vụ phục vụ chuyến bay', 'description' => 'Hoàn tất các điều kiện mặt đất cho điều hành M03.', 'allowed' => true];
        }
        if (Access::allows($u, 'finance')) {
            $tiles[] = ['url' => '/staff/payments', 'title' => 'Thanh toán đặt chuyến', 'description' => 'Đối chiếu chuyển khoản trước khi xuất vé.', 'allowed' => true];
        }
        $flights = $ops->table('flights');
        $airports = $ops->table('airports');
        $routes = $ops->table('routes');
        $plans = $ops->table('flight_seasons');
        if (in_array($u->role, Access::SCOPED, true)) {
            $flights->where('origin', $u->airport_code);
            $airports->where('code', $u->airport_code);
            $routes->where('origin', $u->airport_code);
            $plans->whereIn('route_id', (clone $routes)->select('id'));
        }
        $kpis = $kpiLinks = [];
        $add = function (string $label, int $count, string $url) use (&$kpis, &$kpiLinks) {
            $kpis[$label] = $count;
            $kpiLinks[$label] = $url;
        };
        if (Access::allows($u, 'data_admin')) {
            $add('Sân bay', (clone $airports)->count(), '/workspace/catalog?flow=airports');
            $add('Đường bay', (clone $routes)->count(), '/workspace/catalog?flow=routes');
            $add('Sân bay chờ duyệt', $ops->table('master_changes')->where('entity', 'airports')->where('status', 'PENDING')->count(), '/workspace/catalog?flow=airports');
            $add('Hạ tầng chờ duyệt', $ops->table('master_changes')->whereIn('entity', ['airport_resources', 'airport_infrastructure'])->where('status', 'PENDING')->count(), '/workspace/catalog?flow=infrastructure');
            $add('Đường bay chờ duyệt', $ops->table('master_changes')->where('entity', 'routes')->where('status', 'PENDING')->count(), '/workspace/catalog?flow=routes');
        }
        if (Access::allows($u, 'data_admin', 'operations', 'ops_manager')) {
            $add('Kế hoạch lịch bay', (clone $plans)->count(), '/workspace/planning?tab=seasons');
            $add('Lịch chờ duyệt', (clone $flights)->where('status', 'PENDING')->count(), '/workspace/planning?tab=review');
        }
        if (Access::allows($u, 'operations', 'ops_manager')) {
            $day = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
            $add('Chuyến đã công bố trong ngày', (clone $flights)->whereNotNull('published_at')->where('departure', '>=', $day)->where('departure', '<', $day->copy()->addDay())->count(), '/workspace/operations/flights');
            $add('Chuyến đang khai thác', (clone $flights)->whereIn('status', ['BOARDING', 'GATE_CLOSED', 'DEPARTED'])->count(), '/workspace/operations/flights');
            $add('Chuyến chậm', (clone $flights)->where('status', 'DELAYED')->count(), '/workspace/operations/flights');
            $add('Phương án chờ duyệt', $ops->table('flight_changes')->whereIn('flight_id', (clone $flights)->select('id'))->where('status', 'PENDING')->count(), '/workspace/recovery');
        }

        return compact('tiles', 'kpis', 'kpiLinks');
    }
}
