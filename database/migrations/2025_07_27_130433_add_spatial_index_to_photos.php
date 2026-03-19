<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0) Remove old triggers if present (idempotent)
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bi_geom`");
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bu_geom`");

        // 1) Ensure column exists (don’t blindly add)
        if (!Schema::hasColumn('photos', 'geom')) {
            // MySQL 8+ syntax with SRID attribute; if you’re on MariaDB and this fails,
            // fall back to: "POINT NULL" (without the "SRID 4326" attribute).
            DB::statement("ALTER TABLE `photos` ADD COLUMN `geom` POINT SRID 4326 NULL");
        }

        // 2) Backfill only rows that are wrong/missing
        DB::statement("
            UPDATE `photos`
            SET `geom` = ST_SRID(POINT(`lon`, `lat`), 4326)
            WHERE `lon` IS NOT NULL
              AND `lat` IS NOT NULL
              AND (
                    `geom` IS NULL
                 OR ST_SRID(`geom`) <> 4326
                 OR ST_X(`geom`) <> `lon`
                 OR ST_Y(`geom`) <> `lat`
              )
        ");

        // 2a) Abort with a clear message if any remaining rows still have NULL geom
        //     (because we’re about to make it NOT NULL).
        $nullGeomCount = (int) DB::scalar("SELECT COUNT(*) FROM `photos` WHERE `geom` IS NULL");
        if ($nullGeomCount > 0) {
            // Optional: include a few sample ids for diagnostics
            $sampleIds = collect(DB::select("SELECT id FROM `photos` WHERE `geom` IS NULL LIMIT 5"))
                ->pluck('id')->implode(', ');
            throw new RuntimeException(
                "Cannot set photos.geom NOT NULL: {$nullGeomCount} rows have NULL geom (likely NULL lon/lat).".
                " Fix those coordinates first. Sample ids: {$sampleIds}"
            );
        }

        // 3) Enforce NOT NULL + SRID (skip if already NOT NULL)
        // MySQL doesn’t expose “IF NOT EXISTS” for MODIFY, so just run it.
        DB::statement("ALTER TABLE `photos` MODIFY COLUMN `geom` POINT SRID 4326 NOT NULL");

        // 4) (Re)create SPATIAL INDEX if missing
        if (!$this->indexExists('photos', 'photos_geom_sidx')) {
            DB::statement("ALTER TABLE `photos` ADD SPATIAL INDEX `photos_geom_sidx` (`geom`)");
        }

        // 5) Create robust triggers with validation
        DB::unprepared("
            CREATE TRIGGER `photos_bi_geom`
            BEFORE INSERT ON `photos` FOR EACH ROW
            BEGIN
              IF NEW.`lon` IS NULL OR NEW.`lat` IS NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lon/lat must not be NULL';
              END IF;
              IF NEW.`lon` < -180 OR NEW.`lon` > 180 OR NEW.`lat` < -90 OR NEW.`lat` > 90 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lon/lat out of range';
              END IF;
              SET NEW.`geom` = ST_SRID(POINT(NEW.`lon`, NEW.`lat`), 4326);
            END
        ");

        DB::unprepared("
            CREATE TRIGGER `photos_bu_geom`
            BEFORE UPDATE ON `photos` FOR EACH ROW
            BEGIN
              IF NEW.`lon` IS NULL OR NEW.`lat` IS NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lon/lat must not be NULL';
              END IF;
              IF NEW.`lon` < -180 OR NEW.`lon` > 180 OR NEW.`lat` < -90 OR NEW.`lat` > 90 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lon/lat out of range';
              END IF;
              SET NEW.`geom` = ST_SRID(POINT(NEW.`lon`, NEW.`lat`), 4326);
            END
        ");
    }

    public function down(): void
    {
        // Drop triggers first
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bi_geom`");
        DB::unprepared("DROP TRIGGER IF EXISTS `photos_bu_geom`");

        // Drop index (if present)
        if ($this->indexExists('photos', 'photos_geom_sidx')) {
            DB::statement("ALTER TABLE `photos` DROP INDEX `photos_geom_sidx`");
        }

        // Drop column (if present)
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
