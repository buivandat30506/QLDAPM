<?php

namespace App\Console\Commands;

use App\Modules\Operations\AirportStatusSimulation;
use Illuminate\Console\Command;

class SimulateAirportStatuses extends Command
{
    protected $signature = 'airline:simulate-airport-statuses {--apply : Ghi dữ liệu, mặc định chỉ xem trước} {--clear : Dọn dữ liệu demo còn nguyên fingerprint} {--rebalance : Phân bố lại dữ liệu demo v1/v2 còn nguyên, giữ trạng thái thủ công} {--randomize : Xáo trộn sân bay một lần, lưu ổn định} {--seed= : Seed để xem trước và chạy lại cùng cách xáo trộn}';

    protected $description = 'Tạo, xáo trộn hoặc dọn trạng thái sân bay local/testing, lưu ổn định trong database';

    public function handle(AirportStatusSimulation $simulation): int
    {
        if ($this->option('clear') && ($this->option('rebalance') || $this->option('randomize') || $this->option('seed') !== null)) {
            $this->error('Không kết hợp --clear với --rebalance, --randomize hoặc --seed.');

            return self::FAILURE;
        }
        if ($this->option('seed') !== null && ! $this->option('randomize')) {
            $this->error('--seed chỉ dùng cùng --randomize.');

            return self::FAILURE;
        }
        $seed = $this->option('randomize') ? ($this->option('seed') ?? bin2hex(random_bytes(8))) : null;
        $this->line(json_encode($simulation->run((bool) $this->option('apply'), (bool) $this->option('clear'), (bool) $this->option('rebalance'), $seed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
