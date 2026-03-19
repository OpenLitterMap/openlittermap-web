<?php

namespace App\Console\Commands\Clusters;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckMigrationStatus extends Command
{
    /** @var string */
    protected $signature = 'clustering:check-migration';

    /** @var string */
    protected $description = 'Verify that the clustering migration is fully applied and healthy.';

    /* -------------------------------------------------------------------------
     |  ENTRY POINT
     * ---------------------------------------------------------------------- */
    public function handle(): int
    {
        $this->info('🔎  Checking clustering migration …');
        $this->newLine();

        /* ─────────────────────────────── photos ──────────────────────────── */
        $this->info('📷  Photos');
        foreach (['tile_key'] as $col) {
            $this->checkColumn('photos', $col);
        }
        $this->checkIndex('photos', 'idx_photos_verified_tile');
        $this->checkIndex('photos', 'idx_photos_tile_updated');

        $photosWithoutTileKey = DB::table('photos')
            ->whereNull('tile_key')
            ->whereBetween('lat', [-90, 90])
            ->whereBetween('lon', [-180, 180])
            ->count();

        $photosWithoutTileKey
            ? $this->warn("  ⚠️  {$photosWithoutTileKey} photos still need tile_key")
            : $this->info('  ✅ All valid photos have tile_key');

        $this->newLine();

        /* ────────────────────────────── clusters ─────────────────────────── */
        $this->info('🗂️  Clusters');
        foreach (['tile_key','cell_x','cell_y','location','grid_size','year'] as $col) {
            $this->checkColumn('clusters', $col);
        }

        // basic sanity: NULL tile_key should never happen anymore
        $nullClusters = DB::table('clusters')->whereNull('tile_key')->count();
        if ($nullClusters) {
            $this->warn("  ⚠️  {$nullClusters} clusters have NULL tile_key");
        }

        $this->checkIndex('clusters', 'idx_clusters_spatial');
        $this->checkIndex('clusters', 'idx_zoom_tile');

        // primary-key check
        $pkInfo = $this->getPrimaryKeyInfo('clusters');
        $this->info('  Primary key   : ' . ($pkInfo ?: '— missing —'));
        if ($pkInfo === 'id') {
            $this->warn("  ⚠️  Still using surrogate id PK — composite PK migration has not been applied");
        }

        // legacy columns lingering?
        $legacy = array_filter(
            ['point_count_abbreviated','created_at','id'],
            fn ($c) => Schema::hasColumn('clusters', $c)
        );
        $legacy
            ? $this->warn('  ⚠️  Legacy columns present: '.implode(', ', $legacy))
            : $this->info('  ✅ No legacy columns');

        $this->newLine();

        /* ─────────────────────────── dirty_tiles ─────────────────────────── */
        $this->info('🔄  Dirty-tiles queue');
        if (!Schema::hasTable('dirty_tiles')) {
            $this->error('  ❌ Table missing');
        } else {
            $backlog = DB::table('dirty_tiles')->count();
            $maxRetries = config('clustering.queue.max_tries', 3);
            $stuck = DB::table('dirty_tiles')->where('attempts', '>=', $maxRetries)->count();

            $this->info("  ✅ Table exists");
            $this->info("  📊 Backlog: {$backlog}  |  Stuck (>= {$maxRetries} tries): {$stuck}");
        }

        /* ───────────────────────────── summary ───────────────────────────── */
        $this->newLine();
        $this->info('📋  Summary');

        if ($photosWithoutTileKey) {
            $this->warn('  → Run  php artisan clustering:update --populate');
        }
        if ($nullClusters) {
            $this->warn('  → Some clusters are inconsistent – investigate back-fill');
        }
        if ($pkInfo === 'id') {
            $this->warn('  → Apply the composite PK migration to replace surrogate id');
        }
        if (!$photosWithoutTileKey && !$nullClusters && $pkInfo !== 'id') {
            $this->info('  ✅ Migration looks good!');
        }

        return 0;
    }

    /* -------------------------------------------------------------------------
     |  Helpers
     * ---------------------------------------------------------------------- */
    private function checkColumn(string $table, string $column): void
    {
        Schema::hasColumn($table, $column)
            ? $this->info("  ✅ {$column}")
            : $this->error("  ❌ {$column}");
    }

    private function checkIndex(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c
               FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name   = ?
                AND index_name   = ?',
            [$table, $index]
        )->c ?? 0;

        $exists
            ? $this->info("  ✅ {$index}")
            : $this->warn("  ⚠️  Missing index {$index}");
    }

    private function getPrimaryKeyInfo(string $table): ?string
    {
        $cols = DB::select(
            "SELECT column_name
               FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name   = ?
                AND index_name   = 'PRIMARY'
              ORDER BY seq_in_index",
            [$table]
        );

        return $cols ? implode(', ', array_column($cols, 'column_name')) : null;
    }
}
