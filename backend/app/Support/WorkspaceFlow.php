<?php

namespace App\Support;

final class WorkspaceFlow
{
    public static function definitions(string $section): array
    {
        return match ($section) {
            'catalog' => [
                'airports' => ['title' => 'Danh sách sân bay', 'tables' => ['Sân bay', 'Phiên bản dữ liệu / chờ duyệt'], 'actions' => ['airport', 'master_propose', 'master_approve'], 'entity' => 'airports'],
                'infrastructure' => ['title' => 'Cơ sở hạ tầng', 'tables' => ['Tài nguyên sân bay', 'Phiên bản dữ liệu / chờ duyệt'], 'actions' => ['resource', 'master_propose', 'master_approve'], 'entity' => 'airport_resources'],
                'routes' => ['title' => 'Đường bay', 'tables' => ['Đường bay', 'Phiên bản dữ liệu / chờ duyệt'], 'actions' => ['route', 'master_propose', 'master_approve'], 'entity' => 'routes'],
            ],
            'scheduling' => [
                'planning' => ['title' => 'Lập và công bố lịch bay', 'actions' => ['schedule', 'season', 'submit', 'submit_batch', 'publish', 'publish_batch']],
                'monitoring' => ['title' => 'Theo dõi chuyến bay', 'actions' => ['flight_state']],
            ],
            'crew' => [
                'profiles' => ['title' => 'Hồ sơ phi hành đoàn', 'tables' => ['Năng định & giấy phép', 'Phiên bản dữ liệu / chờ duyệt'], 'actions' => ['crew', 'master_propose', 'master_approve'], 'entity' => 'crew_members'],
                'assignments' => ['title' => 'Phân công phi hành đoàn', 'tables' => ['Phân công tổ bay'], 'actions' => ['assign_crew']],
            ],
            'ground' => [
                'tasks' => ['title' => 'Nhiệm vụ mặt đất', 'actions' => ['ground_task']],
                'tracking' => ['title' => 'Theo dõi công việc', 'actions' => ['ground_state']],
            ],
            'finance' => [
                'transactions' => ['title' => 'Tài chính nghiệp vụ', 'tables' => ['Yêu cầu đổi vé đã thanh toán', 'Yêu cầu hoàn tiền', 'Sổ giao dịch'], 'actions' => ['exchange_decide', 'refund_decide']],
                'reconciliation' => ['title' => 'Đối soát doanh thu', 'tables' => ['Kỳ đối soát', 'Kết quả và sai lệch', 'Sổ giao dịch'], 'actions' => ['reconcile', 'resolve', 'close_period']],
            ],
            default => [],
        };
    }

    public static function includesForm(?array $flow, string $action, array $fields): bool
    {
        if (! $flow) {
            return true;
        }
        if (! in_array($action, $flow['actions'], true)) {
            return false;
        }
        if ($action === 'master_propose' && isset($flow['entity'])) {
            $entity = collect($fields)->firstWhere('name', 'entity')['options']['value'] ?? null;

            return $entity === $flow['entity'];
        }

        return true;
    }
}
