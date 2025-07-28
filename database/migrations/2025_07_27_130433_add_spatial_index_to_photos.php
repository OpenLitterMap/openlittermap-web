<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any previous attempts (idempotent)
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bi_geom`");
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bu_geom`");

        if ($this->indexExists('photos', 'photos_geom_sidx')) {
            DB::statement("ALTER TABLE `photos` DROP INDEX `photos_geom_sidx`");
        }

        // 1) Add a regular POINT column with SRID 4326 (nullable first)
        DB::statement("ALTER TABLE `photos` ADD COLUMN `geom` POINT SRID 4326 NULL");

        // 2) Backfill from existing lon/lat - CORRECT ORDER: POINT(lon, lat)
        DB::statement("
            UPDATE `photos`
            SET `geom` = ST_SRID(POINT(`lon`, `lat`), 4326)
            WHERE `lon` IS NOT NULL AND `lat` IS NOT NULL
        ");

        // 3) Make NOT NULL (required for SPATIAL index)
        DB::statement("ALTER TABLE `photos` MODIFY COLUMN `geom` POINT SRID 4326 NOT NULL");

        // 4) Add SPATIAL index
        DB::statement("ALTER TABLE `photos` ADD SPATIAL INDEX `photos_geom_sidx` (`geom`)");

        // 5) Keep geom in sync on future writes via triggers
        // Using WKT format for clarity and compatibility
        DB::unprepared("
            CREATE TRIGGER `photos_bi_geom`
            BEFORE INSERT ON `photos` FOR EACH ROW
            SET NEW.`geom` = ST_GeomFromText(CONCAT('POINT(', NEW.`lon`, ' ', NEW.`lat`, ')'), 4326)
        ");

        DB::unprepared("
            CREATE TRIGGER `photos_bu_geom`
            BEFORE UPDATE ON `photos` FOR EACH ROW
            SET NEW.`geom` = ST_GeomFromText(CONCAT('POINT(', NEW.`lon`, ' ', NEW.`lat`, ')'), 4326)
        ");
    }

    public function down(): void
    {
        // Drop triggers first
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bi_geom`");
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bu_geom`");

        // Drop index and column
        if ($this->indexExists('photos', 'photos_geom_sidx')) {
            DB::statement("ALTER TABLE `photos` DROP INDEX `photos_geom_sidx`");
        }

        if (Schema::hasColumn('photos', 'geom')) {
            DB::statement("ALTER TABLE `photos` DROP COLUMN `geom`");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [$table, $index]);

        return (int)($row->c ?? 0) > 0;
    }
};
