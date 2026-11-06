<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ExportModule1Sql extends Command
{
    public const TABLES = ['airports', 'airport_resources', 'routes', 'aircraft_models', 'aircrafts',
        'flight_schedules', 'operational_flights', 'crew_members', 'flight_crew_assignments',
        'master_data_records', 'maintenance_orders', 'aircraft_usage_records', 'flight_events',
        'entity_versions', 'audit_logs', 'crew_notifications'];

    protected $signature = 'export:module1-sql {--path= : Output SQL path}';

    protected $description = 'Export Module 1 tables and data to table1.sql and table1.zip';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command requires MySQL or MariaDB.');

            return self::FAILURE;
        }
        $path = $this->option('path') ?: database_path('sql/table1.sql');
        $zipPath = preg_replace('/\\.sql$/i', '', $path).'.zip';
        $tables = self::TABLES;
        try {
            $sql = "-- Smart Airline Module 1 - MySQL / MariaDB\n";
            $sql .= '-- Exported UTC: '.gmdate('Y-m-d H:i:s')."\n";
            $sql .= "-- Import into a fresh database. Existing Module 1 tables are replaced.\n";
            $sql .= "SET NAMES utf8mb4;\nSET @OLD_FK_CHECKS=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            DB::connection()->getPdo()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $sql .= DB::transaction(function () use ($tables) {
                $dump = '';
                foreach ($tables as $table) {
                    // A missing table aborts the entire export.
                    $ddl = DB::selectOne("SHOW CREATE TABLE `{$table}`")->{'Create Table'};
                    $dump .= "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n";
                    $key = DB::getSchemaBuilder()->getColumnListing($table)[0];
                    foreach (DB::table($table)->orderBy($key)->cursor() as $row) {
                        $values = (array) $row;
                        $columns = implode(', ', array_map(fn ($name) => "`{$name}`", array_keys($values)));
                        // Hex literals preserve UTF-8 and special characters regardless of sql_mode.
                        $encoded = implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : "X'".bin2hex((string) $value)."'", $values));
                        $dump .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$encoded});\n";
                    }
                    $dump .= "\n";
                }

                return $dump;
            });
            $sql .= "SET FOREIGN_KEY_CHECKS=@OLD_FK_CHECKS;\n";
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $sql);

            // PharData works with default XAMPP PHP without ext-zip.
            $temporaryZip = $zipPath.'.tmp.zip';
            if (File::exists($temporaryZip)) {
                File::delete($temporaryZip);
            }
            $archive = new \PharData($temporaryZip);
            $archive->addFromString('table1.sql', $sql);
            $archive->compressFiles(\Phar::GZ);
            unset($archive);
            File::move($temporaryZip, $zipPath);
            $this->info("SQL: {$path}");
            $this->info("ZIP: {$zipPath}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
