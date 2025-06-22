<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One migration that:
 *   • adds   photos.tile_key  (generated column + index)
 *   • (re-)creates the        clusters         table
 *   • creates clustering_runs audit table
 *   • adds performance indexes for clustering
 */
return new class extends Migration
{
    /* ─────────────────────────────────────────────────────────────── */
    /*  Version guard                                                 */
    /* ─────────────────────────────────────────────────────────────── */
    private const MIN_MYSQL = '8.0.16';

    public function up(): void
    {
        $this->assertVersion();

        $this->addTileKeyToPhotos();
        $this->addPerformanceIndexesToPhotos();  // NEW
        $this->recreateClustersTable();
        $this->createRunsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('clustering_runs');
        Schema::dropIfExists('clusters');

        // Drop all indexes we created
        $indexes = [
            'idx_verified_tile',
            'idx_clustering_updates',
            'idx_clustering_year',
            'idx_geo_verified'
        ];

        foreach ($indexes as $index) {
            if ($this->indexExists('photos', $index)) {
                DB::statement("ALTER TABLE photos DROP INDEX {$index}");
            }
        }

        // Drop photo_year if it exists
        if (Schema::hasColumn('photos', 'photo_year')) {
            DB::statement('ALTER TABLE photos DROP COLUMN photo_year');
        }

        // Drop tile_key if it exists
        if (Schema::hasColumn('photos', 'tile_key')) {
            DB::statement('ALTER TABLE photos DROP COLUMN tile_key');
        }
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Step 1 – photos tile key                                      */
    /* ─────────────────────────────────────────────────────────────── */
    private function addTileKeyToPhotos(): void
    {
        if (Schema::hasColumn('photos', 'tile_key')) {
            return;
        }

        DB::statement("
            ALTER TABLE photos
            ADD COLUMN tile_key INT UNSIGNED
            GENERATED ALWAYS AS (
                IF(lat IS NULL OR lon IS NULL,
                   NULL,
                   CAST(ROUND((LEAST(GREATEST(lat, -90), 89.999999) + 90) * 4) AS UNSIGNED) * 10000 +
                   CAST(ROUND((LEAST(GREATEST(lon, -180), 179.999999) + 180) * 4) AS UNSIGNED)
                )
            ) STORED
        ");

        DB::statement("
            ALTER TABLE photos
            ADD INDEX idx_verified_tile (verified, tile_key)
        ");
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Step 2 – performance indexes                                  */
    /* ─────────────────────────────────────────────────────────────── */
    private function addPerformanceIndexesToPhotos(): void
    {
        // Add index for finding recently updated tiles
        if (!$this->indexExists('photos', 'idx_clustering_updates')) {
            DB::statement("
                CREATE INDEX idx_clustering_updates
                ON photos(verified, updated_at, tile_key)
            ");
        }

        // Add photo_year column for efficient year-based queries
        if (!Schema::hasColumn('photos', 'photo_year')) {
            DB::statement("
                ALTER TABLE photos
                ADD COLUMN photo_year SMALLINT
                GENERATED ALWAYS AS (YEAR(created_at)) STORED
                AFTER created_at
            ");
        }

        // Add index for year-based clustering
        if (!$this->indexExists('photos', 'idx_clustering_year')) {
            DB::statement("
                CREATE INDEX idx_clustering_year
                ON photos(verified, tile_key, photo_year)
            ");
        }

        // Optional: Geographic queries
        if (!$this->indexExists('photos', 'idx_geo_verified')) {
            DB::statement("
                CREATE INDEX idx_geo_verified
                ON photos(lat, lon, verified)
            ");
        }
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Step 3 – clusters table                                       */
    /* ─────────────────────────────────────────────────────────────── */
    private function recreateClustersTable(): void
    {
        Schema::dropIfExists('clusters');

        DB::statement("
            CREATE TABLE clusters (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tile_key    INT UNSIGNED NOT NULL,
                zoom        TINYINT UNSIGNED NOT NULL,
                cell_x      INT NOT NULL,
                cell_y      INT NOT NULL,
                lat         DOUBLE NOT NULL,
                lon         DOUBLE NOT NULL,
                point_count INT UNSIGNED NOT NULL,
                year        SMALLINT DEFAULT 0,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uniq_cell_with_year (tile_key, zoom, cell_x, cell_y, year),
                KEY idx_tile_zoom (tile_key, zoom),
                KEY idx_zoom_bbox (zoom, lat, lon),
                KEY idx_year      (year),
                KEY idx_tile_updated (tile_key, updated_at),

                CONSTRAINT chk_zoom CHECK (zoom BETWEEN 2 AND 20),
                CONSTRAINT chk_lat  CHECK (lat BETWEEN -90  AND 90),
                CONSTRAINT chk_lon  CHECK (lon BETWEEN -180 AND 180),
                CONSTRAINT chk_point_count CHECK (point_count > 0)
            ) ENGINE=InnoDB
              CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Step 4 – audit table                                          */
    /* ─────────────────────────────────────────────────────────────── */
    private function createRunsTable(): void
    {
        if (Schema::hasTable('clustering_runs')) {
            return;
        }

        DB::statement("
            CREATE TABLE clustering_runs (
                id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_type         ENUM('scheduled','manual','full_rebuild') DEFAULT 'manual',
                status           ENUM('running','completed','failed')      DEFAULT 'running',
                started_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at     TIMESTAMP NULL,
                duration_seconds INT UNSIGNED GENERATED ALWAYS AS (
                    IF(completed_at IS NULL,NULL,
                       TIMESTAMPDIFF(SECOND,started_at,completed_at))
                ) STORED,
                tiles_processed  INT UNSIGNED DEFAULT 0,
                tiles_failed     INT UNSIGNED DEFAULT 0,
                photos_processed BIGINT UNSIGNED DEFAULT 0,
                clusters_created BIGINT UNSIGNED DEFAULT 0,
                peak_memory_mb   DECIMAL(8,2) DEFAULT NULL,
                quality_metrics  JSON DEFAULT NULL,
                error_message    TEXT,
                KEY idx_recent (started_at DESC),
                KEY idx_status (status, started_at DESC)
            ) ENGINE=InnoDB
              CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  Helpers                                                       */
    /* ─────────────────────────────────────────────────────────────── */
    private function assertVersion(): void
    {
        $v = DB::selectOne("SELECT VERSION() AS v")->v;
        $clean = preg_replace('/[^0-9.]/','', $v);

        if (version_compare($clean, self::MIN_MYSQL, '<')) {
            throw new \RuntimeException("MySQL {$v} is too old");
        }
    }

    private function indexExists(string $t, string $i): bool
    {
        return (bool) DB::selectOne("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
            LIMIT 1
        ", [$t, $i]);
    }
};
