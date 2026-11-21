<?php

namespace App\Support;

// Historical classifications only: never used as the active input/type catalogue.
final class InfrastructureLegacyMap
{
    public const CONVERSIONS = [
        'PASSENGER_TERMINAL' => ['TERMINAL', 'terminals', ['terminal_kind' => 'PASSENGER']],
        'CARGO_TERMINAL' => ['TERMINAL', 'terminals', ['terminal_kind' => 'CARGO']],
        'LEVEL' => ['ZONE', 'zones', ['zone_function' => 'LEVEL']],
        'DEPARTURE_HALL' => ['ZONE', 'zones', ['zone_function' => 'DEPARTURE_HALL']],
        'ARRIVAL_HALL' => ['ZONE', 'zones', ['zone_function' => 'ARRIVAL_HALL']],
        'CHECKIN_AREA' => ['ZONE', 'zones', ['zone_function' => 'CHECKIN']],
        'SECURITY_AREA' => ['ZONE', 'zones', ['zone_function' => 'SECURITY']],
        'IMMIGRATION_AREA' => ['ZONE', 'zones', ['zone_function' => 'IMMIGRATION']],
        'CUSTOMS_AREA' => ['ZONE', 'zones', ['zone_function' => 'CUSTOMS']],
        'WAITING_AREA' => ['ZONE', 'zones', ['zone_function' => 'WAITING']],
        'CLAIM_AREA' => ['ZONE', 'zones', ['zone_function' => 'BAGGAGE_CLAIM']],
        'BAG_SORTING_AREA' => ['ZONE', 'zones', ['zone_function' => 'BAG_SORTING']],
        'BAG_MAKEUP_AREA' => ['ZONE', 'zones', ['zone_function' => 'BAG_MAKEUP']],
        'BAG_DROP' => ['COUNTER', 'counters', ['counter_service' => 'BAG_DROP']],
        'ARRIVAL_BELT' => ['BELT', 'belts', ['baggage_flow' => 'ARRIVAL']],
        'DEPARTURE_BELT' => ['BELT', 'belts', ['baggage_flow' => 'DEPARTURE']],
        'TAXILANE' => ['TAXIWAY', 'taxiways', ['taxiway_function' => 'STAND_ACCESS']],
    ];

    public static function archivedTypes(): array
    {
        return json_decode(file_get_contents(database_path('data/infrastructure-types-v1.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function retired(): array
    {
        return array_values(array_diff(array_column(self::archivedTypes(), 'code'), array_keys(InfrastructureCatalog::LABELS)));
    }
}
