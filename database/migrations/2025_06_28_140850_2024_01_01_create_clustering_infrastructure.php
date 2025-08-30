<?php

use App\Models\Cluster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delete all data before creating new table
        Cluster::truncate();

        /* ---------- photos ---------- */

        if (!Schema::hasColumn('photos', 'tile_key')) {
            Schema::table('photos', function (Blueprint $t) {
                $t->unsignedInteger('tile_key')->nullable()->after('lon');
            });

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

            if (!$this->indexExists('photos', 'idx_photos_verified_lat_lon')) {
                DB::statement('CREATE INDEX idx_photos_verified_lat_lon ON photos(verified, lat, lon)');
            }
        }

        /* ---------- clusters: add columns (no backfill needed - table is empty) ---------- */

        // 1. Add columns as NOT NULL with defaults (since table is empty)
        Schema::table('clusters', function (Blueprint $t) {
            if (!Schema::hasColumn('clusters', 'tile_key')) {
                $t->unsignedInteger('tile_key')->default(0);
            }
            if (!Schema::hasColumn('clusters', 'cell_x')) {
                $t->integer('cell_x')->default(0)->after('zoom');
                $t->integer('cell_y')->default(0)->after('cell_x');
            }
        });

        // 2. Remove defaults after adding columns
        DB::statement('ALTER TABLE clusters
            MODIFY tile_key INT UNSIGNED NOT NULL,
            MODIFY cell_x INT NOT NULL,
            MODIFY cell_y INT NOT NULL'
        );

        // 3. Modify year column
        DB::statement('ALTER TABLE clusters
            MODIFY year SMALLINT UNSIGNED NOT NULL DEFAULT 0'
        );

        // 4. Add location column
        if (!Schema::hasColumn('clusters', 'location')) {
            DB::statement("ALTER TABLE clusters ADD COLUMN location POINT NOT NULL AFTER lon");
        }

        // 5. Create spatial index
        if (!$this->indexExists('clusters', 'idx_clusters_spatial')) {
            DB::statement('CREATE SPATIAL INDEX idx_clusters_spatial ON clusters(location)');
        }

        // 6. Add remaining indexes and columns
        DB::statement("
            ALTER TABLE clusters
              ADD UNIQUE KEY uk_cluster (tile_key, zoom, year, cell_x, cell_y),
              ADD COLUMN grid_size DECIMAL(6,3) NOT NULL DEFAULT 0.25,
              ADD INDEX idx_zoom_tile (zoom, tile_key)
        ");

        /* ---------- dirty_tiles table ---------- */

        if (!Schema::hasTable('dirty_tiles')) {
            Schema::create('dirty_tiles', function (Blueprint $t) {
                $t->unsignedInteger('tile_key');
                $t->timestamp('changed_at')->useCurrent();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->primary('tile_key');
                $t->index(['changed_at', 'attempts']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dirty_tiles');

        if ($this->primaryKeyExists('clusters')) {
            DB::statement('ALTER TABLE clusters DROP PRIMARY KEY');
        }

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
            if (Schema::hasColumn('clusters', 'grid_size')) {
                $t->dropColumn('grid_size');
            }

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

        if ($this->indexExists('photos', 'idx_photos_verified_lat_lon')) {
            DB::statement('DROP INDEX idx_photos_verified_lat_lon ON photos');
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

    private function primaryKeyExists(string $table): bool
    {
        $result = DB::selectOne("
        SELECT COUNT(*) AS cnt
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = ?
          AND index_name   = 'PRIMARY'
    ", [$table]);

        return $result && $result->cnt > 0;
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }
};
