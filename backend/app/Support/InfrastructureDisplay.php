<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class InfrastructureDisplay
{
    /** A derived label only; never persist it as a verified name. */
    public static function name(object $record): string
    {
        if (trim($record->name ?? '') !== '') {
            return $record->name;
        }
        $label = self::typeName($record->type_code ?? $record->type ?? '');
        $code = trim($record->code ?? '');
        return preg_match('/^'.preg_quote($label, '/').'\s/iu', $code) ? $code : trim($label.' '.$code);
    }

    public static function typeName(string $type): string
    {
        return match ($type) {
            'GATE' => 'Cổng',
            'STAND' => 'Vị trí đỗ',
            default => InfrastructureCatalog::label($type),
        };
    }

    /** Kept for callers using the previous table helper. */
    public static function tableName(object $record): string
    {
        return self::name($record);
    }

    public static function state(bool $active, ?string $condition): array
    {
        if (! $active) {
            return ['label' => 'Ngừng hiệu lực', 'tone' => 'inactive', 'description' => 'Danh mục ngừng hiệu lực; tình trạng vận hành gốc vẫn được giữ trong hồ sơ.'];
        }

        return match ($condition) {
            'NORMAL' => ['label' => 'Bình thường', 'tone' => 'normal'],
            'RESTRICTED' => ['label' => 'Hạn chế sử dụng', 'tone' => 'high'],
            'MAINTENANCE' => ['label' => 'Đang bảo trì', 'tone' => 'maintenance'],
            'OUT_OF_SERVICE' => ['label' => 'Tạm ngừng hoạt động', 'tone' => 'overload'],
            default => ['label' => 'Chưa xác định', 'tone' => 'unknown'],
        } + ['description' => 'Tình trạng khai thác của hạ tầng.'];
    }

    public static function parentTypes(string $type): array
    {
        return match ($type) {
            'TERMINAL','RUNWAY','APRON' => [],
            'STAND','TAXIWAY' => ['APRON'],
            'ZONE','GATE','COUNTER','BELT','JET_BRIDGE' => ['TERMINAL', 'ZONE'],
            default => [],
        };
    }

    public static function parentChoices(Collection $rows, string $type, ?int $id): Collection
    {
        $byId = $rows->keyBy('id');

        return $rows->filter(function ($row) use ($byId, $type, $id) {
            if (! in_array($row->type_code, self::parentTypes($type), true)) {
                return false;
            }
            $seen = [];
            $next = $row;
            while ($next) {
                if ((int) $next->id === $id || isset($seen[$next->id])) {
                    return false;
                }
                $seen[$next->id] = true;
                $next = $next->parent_id ? $byId->get($next->parent_id) : null;
            }

            return true;
        })->values();
    }

    public static function location(object $record, Collection $parents): string
    {
        if (empty($record->airport_code)) {
            return 'Thiếu thông tin sân bay — cần kiểm tra';
        }
        if (! $record->parent_id) {
            return 'Sân bay '.$record->airport_code;
        }
        $parts = [];
        $seen = isset($record->id) ? [$record->id => true] : [];
        $childType = $record->type_code;
        $next = $record->parent_id;
        while ($next) {
            if (isset($seen[$next]) || ! $parents->has($next)) {
                return 'Liên kết khu vực cần kiểm tra';
            }
            $row = $parents->get($next);
            if (($row->airport_code ?? null) !== $record->airport_code
                || ! in_array($row->type_code, self::parentTypes($childType), true)) {
                return 'Liên kết khu vực cần kiểm tra';
            }
            $seen[$next] = true;
            $parts[] = self::name($row);
            $childType = $row->type_code;
            $next = $row->parent_id;
        }

        return implode(' / ', array_reverse($parts));
    }
}
