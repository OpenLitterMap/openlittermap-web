<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**  Set to false if you want to keep the `id` column for now.  */
    private const DROP_ID_COLUMN = true;

    public function up(): void
    {
        /* ------------------------------------------------------------------
         |  0. Fast introspection helpers
         * ------------------------------------------------------------------*/
        $hasPk = fn () => (int) DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name',   'clusters')
            ->where('index_name',   'PRIMARY')
            ->count() > 0;

        $hasUkCluster = fn () => (int) DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name',   'clusters')
            ->where('index_name',   'uk_cluster')
            ->count() > 0;

        $colExists = fn (string $col) => Schema::hasColumn('clusters', $col);

        /* ------------------------------------------------------------------
         |  1. Make sure `id` is NOT auto-increment
         * ------------------------------------------------------------------*/
        DB::statement("
            ALTER TABLE clusters
            MODIFY id BIGINT UNSIGNED NOT NULL
        ");

        /* ------------------------------------------------------------------
         |  2. Drop old PRIMARY KEY (if any)
         * ------------------------------------------------------------------*/
        if ($hasPk()) {
            DB::statement("ALTER TABLE clusters DROP PRIMARY KEY");
        }

        /* ------------------------------------------------------------------
         |  3. Create / promote composite PRIMARY KEY
         * ------------------------------------------------------------------*/
        if ($hasUkCluster()) {
            // uk_cluster exists – promote & remove the unique index
            DB::statement("
                ALTER TABLE clusters
                  DROP KEY uk_cluster,
                  ADD PRIMARY KEY (tile_key, zoom, year, cell_x, cell_y)
            ");
        } else {
            // unique key already gone – just be sure PK is present
            if (! $hasPk()) {
                DB::statement("
                    ALTER TABLE clusters
                      ADD PRIMARY KEY (tile_key, zoom, year, cell_x, cell_y)
                ");
            }
        }

        /* ------------------------------------------------------------------
         |  4. Remove legacy columns, if any
         * ------------------------------------------------------------------*/
        $legacy = collect(['point_count_abbreviated', 'geohash', 'created_at'])
            ->filter($colExists)
            ->all();

        if ($legacy) {
            DB::statement("
                ALTER TABLE clusters
                  DROP COLUMN " . implode(', DROP COLUMN ', $legacy)
            );
        }

        /* ------------------------------------------------------------------
         |  5. Optionally drop `id`
         * ------------------------------------------------------------------*/
        if (self::DROP_ID_COLUMN && $colExists('id')) {
            DB::statement("ALTER TABLE clusters DROP COLUMN id");
        }
    }

    public function down(): void
    {
        // Intentionally left empty – this change is not meant to be rolled back
        // once applied in production.
    }
};
