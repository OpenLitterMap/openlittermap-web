<?php

namespace App\Console\Commands\Clusters;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimpleSpatialTest extends Command
{
    protected $signature = 'spatial:simple-test';
    protected $description = 'Simple test of spatial setup';

    public function handle(): int
    {
        $this->info("🧪 Simple Spatial Test\n");

        // 1. Basic stats
        $this->info("📊 Data Status:");

        $photos = DB::table('photos')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN location IS NOT NULL THEN 1 ELSE 0 END) as with_location,
            SUM(CASE WHEN lat IS NOT NULL THEN 1 ELSE 0 END) as with_coords
        ')->first();

        $this->line("  Photos total: " . number_format($photos->total));
        $this->line("  With coordinates: " . number_format($photos->with_coords));
        $this->line("  With location column: " . number_format($photos->with_location));

        $clusters = DB::table('clusters')->count();
        $this->line("  Clusters: " . number_format($clusters));

        // 2. List all indexes
        $this->info("\n📑 All Indexes on photos table:");
        $indexes = DB::select("SHOW INDEX FROM photos");
        foreach ($indexes as $idx) {
            if (!in_array($idx->Key_name, ['PRIMARY', 'photos_user_id_index'])) {
                $this->line("  - " . $idx->Key_name);
            }
        }

        // 3. Performance test
        $this->info("\n⚡ Performance Test:");

        // Test 1: Simple query
        $start = microtime(true);
        $count1 = DB::table('photos')
            ->whereBetween('lat', [51.4, 51.6])
            ->whereBetween('lon', [-0.2, 0.0])
            ->where('verified', 2)
            ->count();
        $time1 = round((microtime(true) - $start) * 1000, 2);

        $this->line("  Query 1 (lat/lon first): $count1 photos in {$time1}ms");

        // Test 2: Optimized query (verified first)
        $start = microtime(true);
        $count2 = DB::table('photos')
            ->where('verified', 2)
            ->whereBetween('lat', [51.4, 51.6])
            ->whereBetween('lon', [-0.2, 0.0])
            ->count();
        $time2 = round((microtime(true) - $start) * 1000, 2);

        $this->line("  Query 2 (verified first): $count2 photos in {$time2}ms");

        // 4. Check if we can use spatial functions
        $this->info("\n🔧 Spatial Functions:");
        try {
            $test = DB::selectOne("SELECT ST_X(location) as x, ST_Y(location) as y FROM photos WHERE location IS NOT NULL LIMIT 1");
            $this->line("  ✓ Spatial functions work (sample point: {$test->y}, {$test->x})");
        } catch (\Exception $e) {
            $this->line("  ✗ Spatial functions not available");
        }

        // 5. Ready check
        $this->info("\n✅ Status:");
        if ($photos->with_location > 0 && $photos->with_coords > 0) {
            $this->line("  Spatial columns are populated and ready!");
            $this->line("  Your clustering system will work with improved performance.");

            if ($clusters == 0) {
                $this->info("\n📌 Next step: Run clustering");
                $this->line("  php artisan clusters:refresh --limit=10");
            }
        } else {
            $this->error("  Something went wrong with spatial setup");
        }

        return 0;
    }
}
