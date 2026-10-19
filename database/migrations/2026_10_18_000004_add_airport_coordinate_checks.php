<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE airports ADD CONSTRAINT chk_airport_latitude CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)');
            DB::statement('ALTER TABLE airports ADD CONSTRAINT chk_airport_longitude CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)');
            DB::statement('ALTER TABLE airports ADD CONSTRAINT chk_airport_coordinates CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['chk_airport_latitude', 'chk_airport_longitude', 'chk_airport_coordinates'] as $constraint) {
                DB::statement('ALTER TABLE airports DROP CHECK '.$constraint);
            }
        }
    }
};
