<?php

namespace Database\Seeders;

use App\Support\InfrastructureCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InfrastructureTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('ops')->hasTable('infrastructure_types')) {
            return;
        }
        // Reference data only; retired metadata is removed by the guarded migration.
        foreach (InfrastructureCatalog::types() as $type) {
            DB::connection('ops')->table('infrastructure_types')->updateOrInsert(['code' => $type['code']], $type);
        }
    }
}
