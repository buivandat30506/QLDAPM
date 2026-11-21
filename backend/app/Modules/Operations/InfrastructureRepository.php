<?php

namespace App\Modules\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;

class InfrastructureRepository
{
    public function search(Builder $query, string $search): void
    {
        $term = '%'.addcslashes($search, '\\%_').'%';
        $case = 'CASE type_code';
        $bindings = [];
        foreach (array_keys(InfrastructureCatalog::LABELS) as $type) {
            $case .= ' WHEN ? THEN ?';
            array_push($bindings, $type, InfrastructureDisplay::typeName($type));
        }
        $case .= ' ELSE type_label END';
        $query->where(function ($q) use ($term, $case, $bindings) {
            $q->where('code', 'like', $term)->orWhere('name', 'like', $term)
                ->orWhere(fn ($missing) => $missing->where(fn ($blank) => $blank->whereNull('name')->orWhereRaw("TRIM(name) = ''"))
                    ->whereRaw("CONCAT($case, ' ', code) LIKE ?", [...$bindings, $term]));
        });
    }

    public function listing(string $airport)
    {
        $db = DB::connection('ops');
        $query = $db->table('airport_infrastructure as i')->join('infrastructure_types as t', 't.code', '=', 'i.type_code')->where('i.airport_code', $airport)->selectRaw('i.id, i.id AS canonical_id, NULL AS legacy_source, NULL AS legacy_id, i.airport_code, i.code, i.name, i.type_code, i.type_code AS type, t.label AS type_label, t.group_code, t.category, i.parent_id, i.active, i.technical_state, i.description, i.version');
        foreach (['airport_resources' => 'resources', 'airport_areas' => 'areas'] as $table => $source) {
            $name = $source === 'areas' ? 'l.name' : 'NULL';
            $legacy = $db->table($table.' as l')->join('infrastructure_types as t', 't.code', '=', 'l.type')->where('l.airport_code', $airport)->whereNull('l.infrastructure_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('airport_infrastructure as known')->whereColumn('known.airport_code', 'l.airport_code')->whereColumn('known.code', 'l.code'))
                ->selectRaw("l.id, NULL AS canonical_id, '$source' AS legacy_source, l.id AS legacy_id, l.airport_code, l.code, $name AS name, l.type AS type_code, l.type, t.label AS type_label, t.group_code, t.category, NULL AS parent_id, l.active, NULL AS technical_state, NULL AS description, 0 AS version");
            $other = $source === 'areas' ? 'airport_resources' : 'airport_areas';
            $legacy->whereNotExists(fn ($q) => $q->selectRaw('1')->from($other.' as ambiguous')->whereColumn('ambiguous.airport_code', 'l.airport_code')->whereColumn('ambiguous.code', 'l.code')->whereNull('ambiguous.infrastructure_id'));
            $query->unionAll($legacy);
        }

        return $db->query()->fromSub($query, 'infrastructure');
    }

    public static function url(object $row, array $context = []): string
    {
        return ($row->canonical_id ? '/workspace/catalog/infrastructure/items/'.$row->canonical_id : '/workspace/catalog/infrastructure/'.$row->legacy_source.'/'.$row->legacy_id).'?'.http_build_query($context);
    }
}
