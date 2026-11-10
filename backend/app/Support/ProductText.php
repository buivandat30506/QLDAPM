<?php

namespace App\Support;

final class ProductText
{
    public static function reason(?string $value): string
    {
        // Keep stored notes/audit intact; only known setup captions use product wording.
        return match ($value) {
            'Phân bổ cửa cho lịch thử nghiệm' => 'Phân bổ cổng lên máy bay',
            'Phân bổ quầy thủ tục thử nghiệm' => 'Phân bổ quầy làm thủ tục',
            'Phân bổ vị trí đỗ thử nghiệm' => 'Phân bổ vị trí đỗ máy bay',
            'Cập nhật danh mục thử nghiệm, cần người phê duyệt độc lập' => 'Cập nhật danh mục, cần người phê duyệt độc lập',
            default => $value ?? '',
        };
    }
}
