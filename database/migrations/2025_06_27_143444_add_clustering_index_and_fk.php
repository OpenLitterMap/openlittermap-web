<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This must run AFTER both clustering and active_tiles migrations.
     */
    public function up(): void
    {
        // Add foreign key constraint (only if both tables exist)
        if (Schema::hasTable('clusters') && Schema::hasTable('active_tiles')) {
            // First ensure clusters.tile_key is NOT NULL for valid FK
            DB::statement('DELETE FROM clusters WHERE tile_key IS NULL');
            DB::statement('ALTER TABLE clusters MODIFY tile_key INT UNSIGNED NOT NULL');

            // Check if any orphaned clusters exist
            $orphaned = DB::table('clusters')
                ->leftJoin('active_tiles', 'clusters.tile_key', '=', 'active_tiles.tile_key')
                ->whereNull('active_tiles.tile_key')
                ->count();

            if ($orphaned > 0) {
                // Clean up orphaned clusters first
                DB::delete('
                    DELETE c FROM clusters c
                    LEFT JOIN active_tiles t ON c.tile_key = t.tile_key
                    WHERE t.tile_key IS NULL
                ');
                $this->info("✓ Cleaned up $orphaned orphaned clusters");
            }

            // Now add the foreign key
            if (!$this->foreignKeyExists('clusters', 'fk_clusters_tile_key')) {
                Schema::table('clusters', function (Blueprint $table) {
                    $table->foreign('tile_key', 'fk_clusters_tile_key')
                        ->references('tile_key')
                        ->on('active_tiles')
                        ->onDelete('cascade');
                });
                $this->info('✓ Added foreign key constraint');
            }
        }

        // Add spatial column for photos (as generated column for spatial index)
        if (!Schema::hasColumn('photos', 'location')) {
            // Create NOT NULL generated column for spatial indexing
            DB::statement("
                ALTER TABLE photos
                ADD COLUMN location POINT SRID 4326
                GENERATED ALWAYS AS (
                    CASE
                        WHEN lat IS NOT NULL AND lon IS NOT NULL
                        THEN ST_PointFromText(CONCAT('POINT(', lon, ' ', lat, ')'), 4326)
                        ELSE ST_SRID(POINT(0, 0), 4326)  -- Use POINT function for default
                    END
                ) STORED NOT NULL
            ");

            $this->info('✓ Added spatial column as NOT NULL generated');

            // Create spatial index (will work because column is NOT NULL)
            DB::statement('CREATE SPATIAL INDEX idx_photos_location ON photos(location)');
            $this->info('✓ Added spatial index');
        }

        // Add optimized indexes for clustering queries
        if (!$this->indexExists('clusters', 'idx_clusters_lookup')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->index(['zoom', 'tile_key', 'year'], 'idx_clusters_lookup');
            });
            $this->info('✓ Added clusters lookup index');
        }

        // Add index on verified + tile_key for efficient clustering queries
        if (!$this->indexExists('photos', 'idx_photos_verified_tile')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->index(['verified', 'tile_key'], 'idx_photos_verified_tile');
            });
            $this->info('✓ Added verified/tile index');
        }

        $this->info('✓ Clustering indexes and constraints added successfully');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        if ($this->foreignKeyExists('clusters', 'fk_clusters_tile_key')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->dropForeign('fk_clusters_tile_key');
            });
        }

        // Revert tile_key to nullable
        DB::statement('ALTER TABLE clusters MODIFY tile_key INT UNSIGNED NULL');

        // Drop indexes
        if ($this->indexExists('clusters', 'idx_clusters_lookup')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->dropIndex('idx_clusters_lookup');
            });
        }

        if ($this->indexExists('photos', 'idx_photos_location')) {
            DB::statement('DROP INDEX idx_photos_location ON photos');
        }

        if ($this->indexExists('photos', 'idx_photos_verified_tile')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('idx_photos_verified_tile');
            });
        }

        // Drop location column
        if (Schema::hasColumn('photos', 'location')) {
            DB::statement('ALTER TABLE photos DROP COLUMN location');
        }
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
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

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
                AND table_name = ?
                AND constraint_name = ?
                AND constraint_type = 'FOREIGN KEY'
        ", [$table, $foreignKey]);

        return $result && $result->count > 0;
    }
};
