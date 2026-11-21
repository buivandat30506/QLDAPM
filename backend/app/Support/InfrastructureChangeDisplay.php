<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class InfrastructureChangeDisplay
{
    public static function rows(object $change): array
    {
        $before = json_decode($change->before_data, true) ?? [];
        $after = json_decode($change->after_data, true) ?? [];
        foreach ($before['profiles'] ?? [] as $profile) {
            $before += (array) $profile;
        }
        $before['directions'] = array_column(array_filter($before['directions'] ?? [], fn ($row) => $row['active']), 'designator');
        $before['stand_ids'] = array_column(array_filter($before['links'] ?? [], fn ($row) => in_array($row['relation'], ['GATE_STAND', 'JET_BRIDGE_STAND'], true)), 'target_id');
        $before['gate_ids'] = array_column(array_filter($before['links'] ?? [], fn ($row) => $row['relation'] === 'JET_BRIDGE_GATE'), 'target_id');
        $labels = ['name'=>'Tên riêng', 'parent_id'=>'Vị trí', 'active'=>'Hiệu lực', 'technical_state'=>'Trạng thái', 'description'=>'Mô tả', 'serves_departures'=>'Phục vụ khởi hành', 'serves_arrivals'=>'Phục vụ chuyến đến', 'length_m'=>'Chiều dài (m)', 'width_m'=>'Chiều rộng (m)', 'limitations'=>'Giới hạn', 'directions'=>'Hướng khai thác', 'compatibility_notes'=>'Thông tin phục vụ máy bay', 'asset_reference'=>'Mã tài sản', 'stand_ids'=>'Vị trí đỗ liên kết', 'gate_ids'=>'Cổng liên kết', 'source_reference'=>'Nguồn cung cấp'];
        foreach (InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field => $definition) {
            $labels[$field] = $definition['label'];
        }
        $result = [];
        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;
            $same = ($old === null || $value === null) ? $old === $value : $old == $value;
            if (! isset($labels[$field]) || $same) {
                continue;
            }
            $result[] = ['label'=>$labels[$field], 'before'=>self::value($field, $old, $before['airport_code']), 'after'=>self::value($field, $value, $before['airport_code'])];
        }

        return $result;
    }

    private static function value(string $field, mixed $value, string $airport): string
    {
        if ($field === 'parent_id' && ! $value) {
            return 'Sân bay '.$airport;
        }
        if ($value === null || $value === '' || $value === []) {
            return 'Chưa ghi nhận';
        }
        if (in_array($field, ['parent_id', 'stand_ids', 'gate_ids'], true)) {
            return collect((array) $value)->map(function ($id) use ($airport) {
                $row = DB::connection('ops')->table('airport_infrastructure')->where('airport_code', $airport)->find($id);
                return $row ? InfrastructureDisplay::name($row) : 'Liên kết cần kiểm tra';
            })->implode(', ');
        }
        if ($field === 'active') {
            return $value ? 'Có hiệu lực' : 'Ngừng hiệu lực';
        }
        if (in_array($field, ['serves_departures', 'serves_arrivals'], true)) {
            return $value ? 'Có' : 'Không';
        }
        if ($field === 'technical_state') {
            return InfrastructureCatalog::CONDITIONS[$value] ?? 'Chưa xác định';
        }
        if (isset(InfrastructureCatalog::ATTRIBUTE_OPTIONS[$field])) {
            return InfrastructureCatalog::ATTRIBUTE_OPTIONS[$field]['options'][$value] ?? 'Cần kiểm tra';
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
