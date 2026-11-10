<?php

namespace Database\Seeders;

use App\Modules\Shared\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AirportIdentifierSeeder extends Seeder
{
    public function run(): void
    {
        $result = $this->synchronize(true);
        $this->command?->line(json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function synchronize(bool $apply = false): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Airport identifier import is restricted to local/testing.');
        }
        $source = json_decode(file_get_contents(database_path('data/vietnam-airport-identifiers.json')), true, flags: JSON_THROW_ON_ERROR);
        $db = DB::connection('ops');

        return $db->transaction(function () use ($db, $source, $apply) {
            $airports = $db->table('airports')->whereIn('code', array_keys($source['airports']))->orderBy('code')->lockForUpdate()->get();
            $updates = [];
            $conflicts = [];
            foreach ($airports as $airport) {
                $expected = ['icao' => $source['airports'][$airport->code], 'country_code' => $source['country_code']];
                $changes = [];
                $conflict = false;
                foreach ($expected as $field => $value) {
                    if ($airport->$field === null || $airport->$field === '') {
                        $changes[$field] = $value;
                    } elseif ($value !== $airport->$field) {
                        $conflicts[] = $airport->code.'.'.$field;
                        $conflict = true;
                    }
                }
                if ($conflict || ! $changes) {
                    continue;
                }
                if (isset($changes['icao']) && $db->table('airports')->where('icao', $changes['icao'])->where('code', '!=', $airport->code)->exists()) {
                    throw new RuntimeException('ICAO already assigned to another airport: '.$changes['icao']);
                }
                $updates[$airport->code] = $changes;
                if ($apply) {
                    $db->table('airports')->where('code', $airport->code)->update($changes);
                    Audit::record('airport.identifiers.imported', $airport->code, json_encode($airport, JSON_THROW_ON_ERROR), json_encode(array_merge((array) $airport, $changes), JSON_THROW_ON_ERROR), null, 'ops');
                }
            }

            return ['apply' => $apply, 'updated_airports' => count($updates), 'updates' => $updates, 'preserved_conflicts' => $conflicts, 'sources' => array_intersect_key($source, array_flip(['verified_on', 'icao_source', 'country_source', 'country_standard']))];
        }, 3);
    }
}
