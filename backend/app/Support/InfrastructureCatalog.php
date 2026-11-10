<?php

namespace App\Support;

final class InfrastructureCatalog
{
    public const GROUPS = ['terminal' => 'Nhà ga và khu vực', 'passenger' => 'Cổng và quầy phục vụ', 'baggage' => 'Hệ thống hành lý', 'airfield' => 'Khu bay và sân đỗ'];

    public const LABELS = ['TERMINAL' => 'Nhà ga', 'ZONE' => 'Khu vực nhà ga', 'GATE' => 'Cổng lên/xuống máy bay', 'COUNTER' => 'Quầy làm thủ tục', 'JET_BRIDGE' => 'Cầu ống lồng', 'BELT' => 'Băng chuyền hành lý', 'RUNWAY' => 'Đường băng', 'TAXIWAY' => 'Đường lăn', 'APRON' => 'Sân đỗ máy bay', 'STAND' => 'Vị trí đỗ máy bay'];
    public const CODE_PREFIXES = ['TERMINAL'=>'T', 'ZONE'=>'Z', 'GATE'=>'G', 'COUNTER'=>'C', 'JET_BRIDGE'=>'JB', 'BELT'=>'B', 'RUNWAY'=>'RWY', 'TAXIWAY'=>'TWY', 'APRON'=>'AP', 'STAND'=>'P'];

    public const ATTRIBUTE_OPTIONS = [
        'terminal_scope' => ['label' => 'Phạm vi phục vụ', 'options' => ['DOMESTIC' => 'Quốc nội', 'INTERNATIONAL' => 'Quốc tế', 'BOTH' => 'Quốc nội và quốc tế']],
        'terminal_kind' => ['label' => 'Loại nhà ga', 'options' => ['PASSENGER' => 'Hành khách', 'CARGO' => 'Hàng hóa', 'MIXED' => 'Kết hợp']],
        'zone_function' => ['label' => 'Chức năng khu vực', 'options' => ['LEVEL' => 'Tầng nhà ga', 'DEPARTURE_HALL' => 'Sảnh khởi hành', 'ARRIVAL_HALL' => 'Sảnh đến', 'CHECKIN' => 'Làm thủ tục', 'SECURITY' => 'An ninh', 'IMMIGRATION' => 'Xuất nhập cảnh', 'CUSTOMS' => 'Hải quan', 'WAITING' => 'Chờ hành khách', 'BAGGAGE_CLAIM' => 'Nhận hành lý', 'BAG_SORTING' => 'Phân loại hành lý', 'BAG_MAKEUP' => 'Tập kết hành lý', 'OVERSIZE_BAGGAGE' => 'Phục vụ hành lý quá khổ', 'OTHER' => 'Khác']],
        'counter_service' => ['label' => 'Chức năng quầy', 'options' => ['CHECKIN' => 'Làm thủ tục', 'BAG_DROP' => 'Ký gửi hành lý', 'COMBINED' => 'Kết hợp']],
        'baggage_flow' => ['label' => 'Chức năng băng chuyền', 'options' => ['ARRIVAL' => 'Hành lý đến', 'DEPARTURE' => 'Hành lý đi', 'BOTH' => 'Kết hợp đi và đến']],
        'taxiway_function' => ['label' => 'Chức năng đường lăn', 'options' => ['GENERAL' => 'Đường lăn chung', 'APRON' => 'Đường lăn sân đỗ', 'STAND_ACCESS' => 'Tiếp cận vị trí đỗ', 'EXIT' => 'Thoát đường băng']],
    ];

    public const PROFILE_FIELDS = [
        'TERMINAL' => ['terminal_kind', 'terminal_scope', 'serves_departures', 'serves_arrivals'], 'ZONE' => ['zone_function'],
        'GATE' => ['serves_departures', 'serves_arrivals', 'stand_ids'], 'COUNTER' => ['counter_service'],
        'JET_BRIDGE' => ['asset_reference', 'gate_ids', 'stand_ids'], 'BELT' => ['baggage_flow'], 'RUNWAY' => ['length_m', 'width_m', 'limitations', 'directions'],
        'TAXIWAY' => ['taxiway_function'], 'APRON' => [], 'STAND' => ['compatibility_notes'],
    ];

    public const PROFILE_TABLES = ['TERMINAL' => 'terminals', 'ZONE' => 'zones', 'GATE' => 'gates', 'COUNTER' => 'counters', 'JET_BRIDGE' => 'assets', 'BELT' => 'belts', 'RUNWAY' => 'runways', 'TAXIWAY' => 'taxiways', 'STAND' => 'stands'];

    public static function label(string $code): string
    {
        if (isset(self::LABELS[$code])) {
            return self::LABELS[$code];
        }
        foreach (InfrastructureLegacyMap::archivedTypes() as $row) {
            if ($row['code'] === $code) {
                return $row['label'];
            }
        }

        return $code;
    }

    public const CONDITIONS = ['NORMAL' => 'Bình thường', 'MAINTENANCE' => 'Đang bảo trì', 'RESTRICTED' => 'Hạn chế sử dụng', 'OUT_OF_SERVICE' => 'Tạm ngừng hoạt động'];

    public const SCHEDULED_TYPES = ['GATE', 'COUNTER', 'STAND'];

    public static function types(): array
    {
        $groups = [
            'terminal' => ['TERMINAL' => self::LABELS['TERMINAL'], 'ZONE' => self::LABELS['ZONE']],
            'passenger' => ['GATE' => self::LABELS['GATE'], 'COUNTER' => self::LABELS['COUNTER'], 'JET_BRIDGE' => self::LABELS['JET_BRIDGE']],
            'baggage' => ['BELT' => self::LABELS['BELT']],
            'airfield' => ['RUNWAY' => self::LABELS['RUNWAY'], 'TAXIWAY' => self::LABELS['TAXIWAY'], 'APRON' => self::LABELS['APRON'], 'STAND' => self::LABELS['STAND']],
        ];
        $rows = [];
        foreach ($groups as $group => $types) {
            foreach ($types as $code => $label) {
                $category = in_array($code, ['GATE', 'COUNTER', 'STAND', 'BELT'], true) ? 'resource' : ($code === 'JET_BRIDGE' ? 'asset' : 'facility');
                $rows[$code] = ['code' => $code, 'label' => $label, 'group_code' => $group, 'category' => $category, 'allocation_policy' => in_array($code, self::SCHEDULED_TYPES, true) ? 'DEPARTURE_WINDOW' : null, 'basis' => 'OFFICIAL'];
            }
        }

        return $rows;
    }
}
