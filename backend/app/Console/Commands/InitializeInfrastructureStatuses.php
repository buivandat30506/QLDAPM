<?php

namespace App\Console\Commands;

use App\Modules\Operations\InfrastructureStatusInitialization;
use Illuminate\Console\Command;

class InitializeInfrastructureStatuses extends Command
{
    protected $signature = 'airline:initialize-infrastructure-statuses {--apply : Xác nhận ghi trạng thái ban đầu}';

    protected $description = 'Khởi tạo Bình thường cho hạ tầng có hiệu lực chưa được thiết lập trạng thái';

    public function handle(InfrastructureStatusInitialization $service): int
    {
        $this->line(json_encode($service->run((bool) $this->option('apply')), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
