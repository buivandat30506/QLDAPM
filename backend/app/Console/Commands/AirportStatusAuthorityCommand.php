<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AirportStatusAuthority;
use Illuminate\Console\Command;

class AirportStatusAuthorityCommand extends Command
{
    protected $signature = 'airline:airport-status-authority {user : ID người được bổ nhiệm} {position : airport_director hoặc system_director} {--airport=* : Mã sân bay, có thể lặp lại} {--all-airports : Chỉ cho system_director, phạm vi toàn hệ thống} {--revoke : Thu hồi đúng phạm vi chỉ định} {--reason= : Căn cứ bổ nhiệm hoặc thu hồi}';

    protected $description = 'Bổ nhiệm/thu hồi quyền airport.operational_status.update; không thay đổi primary role hoặc Multi-role';

    public function handle(): int
    {
        $user = User::findOrFail($this->argument('user'));
        $scopes = $this->option('all-airports') ? ['*'] : array_unique($this->option('airport'));
        if (! $scopes || ($this->option('all-airports') && $this->option('airport'))) {
            $this->error('Chỉ định --airport=HAN (có thể lặp) hoặc --all-airports.');

            return self::FAILURE;
        }
        foreach ($scopes as $scope) {
            AirportStatusAuthority::assign($user, $this->argument('position'), $scope, (string) $this->option('reason'), ! $this->option('revoke'));
        }
        $this->info('Đã ghi nhận bổ nhiệm/thu hồi có chủ đích; không đổi các quyền phân hệ khác.');

        return self::SUCCESS;
    }
}
