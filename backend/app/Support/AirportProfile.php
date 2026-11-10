<?php

namespace App\Support;

final class AirportProfile
{
    public const FIELDS = ['name'=>'Tên sân bay', 'city'=>'Thành phố', 'timezone'=>'Múi giờ'];

    public static function rules(): array
    {
        return ['name'=>'required|string|max:120', 'city'=>'required|string|max:100', 'timezone'=>'required|timezone', 'reason'=>'required|string|min:5|max:255'];
    }

    public static function snapshot(object $airport): array
    {
        return array_intersect_key((array) $airport, self::FIELDS);
    }

    public static function differences(array $before, array $after): array
    {
        $labels = self::FIELDS + ['icao'=>'Mã ICAO', 'country_code'=>'Mã quốc gia', 'latitude'=>'Vĩ độ', 'longitude'=>'Kinh độ', 'operational_status'=>'Trạng thái vận hành', 'operational_status_note'=>'Ghi chú'];
        $result = [];
        foreach ($after as $field=>$value) {
            $old = $before[$field] ?? null;
            if (! isset($labels[$field]) || $old === $value) {
                continue;
            }
            $format = fn ($v) => $v === null || $v === '' ? 'Chưa ghi nhận' : ($field === 'operational_status' ? AirportWorkspace::status($v)['label'] : (string) $v);
            $result[] = ['label'=>$labels[$field], 'before'=>$format($old), 'after'=>$format($value)];
        }
        return $result;
    }

    public static function decode(?string $value): array
    {
        $decoded = json_decode($value ?? '', true);
        return is_array($decoded) ? $decoded : [];
    }
}
