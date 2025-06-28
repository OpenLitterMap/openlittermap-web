<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- photos ---------- */

        if (!Schema::hasColumn('photos', 'tile_key')) {
            Schema::table('photos', function (Blueprint $t) {
                $t->unsignedInteger('tile_key')->nullable()->after('lon');
            });

            // Add indexes only if they don't exist
            if (!$this->indexExists('photos', 'idx_photos_verified_tile')) {
                Schema::table('photos', function (Blueprint $t) {
                    $t->index(['verified', 'tile_key'], 'idx_photos_verified_tile');
                });
            }

            if (!$this->indexExists('photos', 'idx_photos_tile_updated')) {
                Schema::table('photos', function (Blueprint $t) {
                    $t->index(['tile_key', 'updated_at'], 'idx_photos_tile_updated');
                });
            }
        }

        /* ---------- clusters: add columns online, fill, then switch PK ---------- */

        // 1. Add columns as NULL-able (instant)
        Schema::table('clusters', function (Blueprint $t) {
            if (!Schema::hasColumn('clusters', 'tile_key')) {
                $t->unsignedInteger('tile_key')->nullable()->after('id');
            }
            if (!Schema::hasColumn('clusters', 'cell_x')) {
                $t->integer('cell_x')->nullable()->after('zoom');
                $t->integer('cell_y')->nullable()->after('cell_x');
            }
        });

        // 2. Backfill with range-based updates (no gap locks)
        $this->info('Backfilling cluster tile keys (range-based)...');
        $batchSize = 10000;
        $totalUpdated = 0;
        $lastId = 0;

        do {
            // Get max ID for this batch
            $maxId = DB::table('clusters')
                ->where('id', '>', $lastId)
                ->whereNull('tile_key')
                ->orderBy('id')
                ->limit($batchSize)
                ->max('id');

            if (!$maxId) {
                break;
            }

            // Update using ID range (no gap locks)
            $affected = DB::update('
                UPDATE clusters
                SET tile_key = FLOOR((LEAST(lat, 89.999999) + 90) / 0.25) * 1440 +
                               FLOOR((LEAST(lon, 179.999999) + 180) / 0.25),
                    cell_x = FLOOR((LEAST(lon, 179.999999) + 180) / 0.25),
                    cell_y = FLOOR((LEAST(lat, 89.999999) + 90) / 0.25)
                WHERE id > ? AND id <= ? AND tile_key IS NULL
            ', [$lastId, $maxId]);

            $totalUpdated += $affected;
            $lastId = $maxId;

            if ($affected > 0) {
                $this->info("  Updated $totalUpdated rows...");
            }
        } while ($affected > 0);

        $this->info("✓ Backfilled $totalUpdated cluster rows");

        // 3. Make columns NOT NULL after backfill (in-place)
        DB::statement('ALTER TABLE clusters
            MODIFY tile_key INT UNSIGNED NOT NULL,
            MODIFY cell_x INT NOT NULL,
            MODIFY cell_y INT NOT NULL'
        );

        // 4. Modify year column (in-place)
        DB::statement('ALTER TABLE clusters
            MODIFY year SMALLINT UNSIGNED NOT NULL DEFAULT 0'
        );

        // 5-A  add a plain nullable POINT column first
        if (!Schema::hasColumn('clusters', 'location')) {
            DB::statement("ALTER TABLE clusters ADD COLUMN location POINT NULL AFTER lon");
        }

        /* 5-B  back-fill existing rows */
        DB::statement("
            UPDATE clusters
            SET location = ST_PointFromText(CONCAT('POINT(', lon, ' ', lat, ')'))
            WHERE lat IS NOT NULL AND lon IS NOT NULL AND location IS NULL
        ");

        /* >>> 5-C  make it NOT NULL (tiny copy, but required for the index) */
        DB::statement("ALTER TABLE clusters MODIFY location POINT NOT NULL");


        // 5-C  create the spatial index
        DB::statement("CREATE SPATIAL INDEX idx_clusters_spatial ON clusters(location)");

        /* -----------------------------------------------------------------
           5-D  add the remaining keys / helper column *in a new ALTER*.
           Older servers can’t mix SPATIAL INDEX with UNIQUE KEY + generated
           column in one statement.  Doing it separately is safe everywhere.
        -------------------------------------------------------------------*/
        DB::statement("
            ALTER TABLE clusters
              ADD UNIQUE KEY uk_cluster (tile_key, zoom, year, cell_x, cell_y),
              ADD COLUMN grid_size DECIMAL(4,3) NULL,
              ADD INDEX idx_zoom_tile (zoom, tile_key)
        ");

        /* initialise grid_size once, then leave it as a normal column */
        DB::statement("
            UPDATE clusters
            SET grid_size = CASE zoom
                              WHEN 8  THEN 1.0
                              WHEN 12 THEN 0.25
                              WHEN 16 THEN 0.05
                              ELSE 0.25
                            END
            WHERE grid_size IS NULL
        ");

        // 6. Primary key migration deferred (see separate command)
        $this->info('');
        $this->info('⚠️  IMPORTANT: To complete the primary key migration:');
        $this->info('1. Deploy application code that uses the new composite key');
        $this->info('2. Run: php artisan clustering:complete-pk-migration');

        /* ---------- dirty_tiles with better queue handling ---------- */

        if (!Schema::hasTable('dirty_tiles')) {
            Schema::create('dirty_tiles', function (Blueprint $t) {
                $t->unsignedInteger('tile_key');
                $t->timestamp('changed_at')->useCurrent();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->primary('tile_key'); // Single PK for simpler queue
                $t->index(['changed_at', 'attempts']); // For efficient queries
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dirty_tiles');

        // Remove columns and indexes
        if ($this->indexExists('clusters', 'uk_cluster')) {
            DB::statement('ALTER TABLE clusters DROP KEY uk_cluster');
        }

        if ($this->indexExists('clusters', 'idx_clusters_spatial')) {
            DB::statement('DROP INDEX idx_clusters_spatial ON clusters');
        }

        if ($this->indexExists('clusters', 'idx_zoom_tile')) {
            DB::statement('DROP INDEX idx_zoom_tile ON clusters');
        }

        if (Schema::hasColumn('clusters', 'location')) {
            DB::statement('ALTER TABLE clusters DROP COLUMN location');
        }

        // Restore year column
        DB::statement('ALTER TABLE clusters MODIFY year YEAR NULL');

        // Drop new columns
        Schema::table('clusters', function (Blueprint $t) {
            if (Schema::hasColumn('clusters', 'tile_key')) {
                $t->dropColumn(['tile_key', 'cell_x', 'cell_y']);
            }
        });

        // Photos rollback
        if (Schema::hasColumn('photos', 'tile_key')) {
            if ($this->indexExists('photos', 'idx_photos_verified_tile')) {
                Schema::table('photos', function (Blueprint $t) {
                    $t->dropIndex('idx_photos_verified_tile');
                });
            }

            if ($this->indexExists('photos', 'idx_photos_tile_updated')) {
                Schema::table('photos', function (Blueprint $t) {
                    $t->dropIndex('idx_photos_tile_updated');
                });
            }

            Schema::table('photos', function (Blueprint $t) {
                $t->dropColumn('tile_key');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?
        ", [$table, $index]);

        return $result && $result->count > 0;
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }
};
