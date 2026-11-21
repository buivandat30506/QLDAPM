<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Operations\InfrastructureFixtures;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PopulateInfrastructure extends Command
{
    protected $signature = 'airline:infrastructure-fixtures {--airport=BMV : Sân bay đã tồn tại} {--all : Lấy toàn bộ sân bay trong DB, chỉ thêm loại còn thiếu} {--missing-only : Chỉ bổ sung loại còn thiếu tại một sân bay} {--actor= : ID quản trị dữ liệu khi ghi hoặc dọn} {--apply : Ghi dữ liệu; mặc định chỉ kiểm tra} {--clear : Kiểm tra hoặc dọn riêng bộ thử nghiệm}';
    protected $description = 'Tạo hoặc dọn kịch bản đủ 10 loại hạ tầng, chỉ trong database local/test đã bật riêng';

    public function handle(InfrastructureFixtures $fixtures): int
    {
        $actor = $this->option('actor') ? User::findOrFail($this->option('actor')) : null;
        try {
            $result = $this->option('all')
                ? $fixtures->runAll((bool) $this->option('apply'), (bool) $this->option('clear'), $actor)
                : $fixtures->run((string) $this->option('airport'), (bool) $this->option('apply'), (bool) $this->option('clear'), $actor, (bool) $this->option('missing-only'));
        } catch (HttpExceptionInterface $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return ! empty($result['blocked']) ? self::FAILURE : self::SUCCESS;
    }
}
