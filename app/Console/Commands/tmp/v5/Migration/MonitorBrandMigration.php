<?php

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorBrandMigration extends Command
{
    protected $signature = 'olm:v5:monitor-brands
                            {--live : Show live statistics}
                            {--sample=10 : Number of sample photos to show}';

    protected $description = 'Monitor brand migration quality and match rates';

    public function handle()
    {
        if ($this->option('live')) {
            $this->monitorLive();
        } else {
            $this->showDashboard();
        }
    }

    private function showDashboard(): void
    {
        $this->info('Brand Migration Dashboard');
        $this->info('=========================');

        // Overall Statistics
        $this->showOverallStats();

        // Match Quality
        $this->showMatchQuality();

        // Top Brands Performance
        $this->showTopBrandsPerformance();

        // Sample Photos
        $this->showSamplePhotos();

        // Recommendations
        $this->showRecommendations();
    }

    private function showOverallStats(): void
    {
        $this->info("\n📊 Overall Statistics");
        $this->info("--------------------");

        // Total brands in system
        $totalBrands = DB::table('brandslist')->count();
        $brandsWithRelationships = DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->distinct('taggable_id')
            ->count('taggable_id');

        // Photos with brands
        $photosWithBrands = DB::table('custom_tags')
            ->where('tag', 'like', 'brand:%')
            ->distinct('photo_id')
            ->count('photo_id');

        // Successful attachments
        $brandAttachments = DB::table('photo_tag_extra_tags')
            ->where('tag_type', 'brand')
            ->count();

        // Unmatched brands (in brands-only category)
        $unmatchedBrands = DB::table('photo_tags')
            ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
            ->where('categories.key', 'brands')
            ->sum('photo_tags.quantity');

        $this->line("Total brands in system: " . number_format($totalBrands));
        $this->line("Brands with relationships: " . number_format($brandsWithRelationships) .
            " (" . round($brandsWithRelationships / $totalBrands * 100, 1) . "%)");
        $this->line("Photos with brands: " . number_format($photosWithBrands));
        $this->line("Successful brand attachments: " . number_format($brandAttachments));
        $this->line("Unmatched brands: " . number_format($unmatchedBrands));
    }

    private function showMatchQuality(): void
    {
        $this->info("\n🎯 Match Quality");
        $this->info("---------------");

        // Calculate match rate for recent photos
        $recentPhotos = DB::table('photo_tags')
            ->select('photo_id')
            ->distinct()
            ->orderByDesc('created_at')
            ->limit(1000)
            ->pluck('photo_id');

        $stats = [
            'perfect_match' => 0,
            'partial_match' => 0,
            'no_match' => 0,
        ];

        foreach ($recentPhotos as $photoId) {
            // Count brands in photo
            $brandCount = DB::table('custom_tags')
                ->where('photo_id', $photoId)
                ->where('tag', 'like', 'brand:%')
                ->count();

            if ($brandCount == 0) continue;

            // Count matched brands (attached to objects)
            $matchedCount = DB::table('photo_tag_extra_tags')
                ->join('photo_tags', 'photo_tag_extra_tags.photo_tag_id', '=', 'photo_tags.id')
                ->where('photo_tags.photo_id', $photoId)
                ->where('photo_tag_extra_tags.tag_type', 'brand')
                ->distinct('tag_type_id')
                ->count('tag_type_id');

            if ($matchedCount == $brandCount) {
                $stats['perfect_match']++;
            } elseif ($matchedCount > 0) {
                $stats['partial_match']++;
            } else {
                $stats['no_match']++;
            }
        }

        $total = array_sum($stats);
        if ($total > 0) {
            $this->line("Perfect matches: {$stats['perfect_match']} (" .
                round($stats['perfect_match'] / $total * 100, 1) . "%)");
            $this->line("Partial matches: {$stats['partial_match']} (" .
                round($stats['partial_match'] / $total * 100, 1) . "%)");
            $this->line("No matches: {$stats['no_match']} (" .
                round($stats['no_match'] / $total * 100, 1) . "%)");
        }
    }

    private function showTopBrandsPerformance(): void
    {
        $this->info("\n🏆 Top Brands Performance");
        $this->info("------------------------");

        // Get top brands by usage
        $topBrands = DB::table('custom_tags')
            ->selectRaw("SUBSTRING(tag, 7) as brand, COUNT(*) as occurrences")
            ->where('tag', 'like', 'brand:%')
            ->groupBy('brand')
            ->orderByDesc('occurrences')
            ->limit(10)
            ->get();

        $data = [];
        foreach ($topBrands as $brand) {
            // Check if brand has relationships
            $brandId = DB::table('brandslist')->where('key', $brand->brand)->value('id');
            $hasRelationships = false;
            $matchRate = 0;

            if ($brandId) {
                $hasRelationships = DB::table('taggables')
                    ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                    ->where('taggable_id', $brandId)
                    ->exists();

                // Calculate match rate
                $matched = DB::table('photo_tag_extra_tags')
                    ->where('tag_type', 'brand')
                    ->where('tag_type_id', $brandId)
                    ->count();

                $matchRate = $brand->occurrences > 0
                    ? round($matched / $brand->occurrences * 100, 1)
                    : 0;
            }

            $data[] = [
                $brand->brand,
                $brand->occurrences,
                $hasRelationships ? '✓' : '✗',
                $matchRate . '%',
            ];
        }

        $this->table(['Brand', 'Occurrences', 'Has Rules', 'Match Rate'], $data);
    }

    private function showSamplePhotos(): void
    {
        $limit = $this->option('sample');
        $this->info("\n📸 Sample Photos (Last {$limit})");
        $this->info("------------------------------");

        $photos = DB::table('photo_tags')
            ->select('photo_id')
            ->distinct()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('photo_id');

        foreach ($photos as $photoId) {
            $this->analyzeSamplePhoto($photoId);
        }
    }

    private function analyzeSamplePhoto(int $photoId): void
    {
        // Get brands in photo
        $brands = DB::table('custom_tags')
            ->where('photo_id', $photoId)
            ->where('tag', 'like', 'brand:%')
            ->pluck('tag')
            ->map(fn($t) => substr($t, 6))
            ->toArray();

        if (empty($brands)) {
            return;
        }

        // Get matched brands
        $matched = DB::table('photo_tag_extra_tags')
            ->join('photo_tags', 'photo_tag_extra_tags.photo_tag_id', '=', 'photo_tags.id')
            ->join('brandslist', 'photo_tag_extra_tags.tag_type_id', '=', 'brandslist.id')
            ->where('photo_tags.photo_id', $photoId)
            ->where('photo_tag_extra_tags.tag_type', 'brand')
            ->pluck('brandslist.key')
            ->toArray();

        $matchRate = count($brands) > 0 ? round(count($matched) / count($brands) * 100) : 0;

        $this->line("\nPhoto #{$photoId}:");
        $this->line("  Brands: " . implode(', ', $brands));
        $this->line("  Matched: " . (empty($matched) ? 'none' : implode(', ', $matched)));
        $this->line("  Match rate: {$matchRate}%");
    }

    private function showRecommendations(): void
    {
        $this->info("\n💡 Recommendations");
        $this->info("-----------------");

        // Find brands needing relationships
        $brandsWithoutRules = DB::table('custom_tags')
            ->selectRaw("SUBSTRING(tag, 7) as brand, COUNT(*) as count")
            ->where('tag', 'like', 'brand:%')
            ->groupBy('brand')
            ->orderByDesc('count')
            ->limit(100)
            ->get()
            ->filter(function($brand) {
                $brandId = DB::table('brandslist')->where('key', $brand->brand)->value('id');
                if (!$brandId) return true;

                return !DB::table('taggables')
                    ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                    ->where('taggable_id', $brandId)
                    ->exists();
            })
            ->take(5);

        if ($brandsWithoutRules->count() > 0) {
            $this->warn("⚠️  Top brands missing relationships:");
            foreach ($brandsWithoutRules as $brand) {
                $this->line("   - {$brand->brand} ({$brand->count} photos)");
            }
            $this->line("\n   Run: php artisan olm:v5:build-relationships --export");
        } else {
            $this->info("✅ All top brands have relationships defined!");
        }
    }

    private function monitorLive(): void
    {
        $this->info("Live Migration Monitor (Press Ctrl+C to exit)");
        $this->info("=============================================\n");

        $lastPhotoId = 0;

        while (true) {
            // Get latest migrated photo
            $latest = DB::table('photo_tags')
                ->where('id', '>', $lastPhotoId)
                ->orderBy('id')
                ->first();

            if ($latest) {
                $lastPhotoId = $latest->id;
                $this->analyzeLivePhoto($latest->photo_id);
            }

            sleep(1);
        }
    }

    private function analyzeLivePhoto(int $photoId): void
    {
        $brands = DB::table('custom_tags')
            ->where('photo_id', $photoId)
            ->where('tag', 'like', 'brand:%')
            ->count();

        if ($brands == 0) return;

        $matched = DB::table('photo_tag_extra_tags')
            ->join('photo_tags', 'photo_tag_extra_tags.photo_tag_id', '=', 'photo_tags.id')
            ->where('photo_tags.photo_id', $photoId)
            ->where('photo_tag_extra_tags.tag_type', 'brand')
            ->count();

        $rate = $brands > 0 ? round($matched / $brands * 100) : 0;
        $status = $rate == 100 ? '✓' : ($rate > 0 ? '~' : '✗');

        $timestamp = now()->format('H:i:s');
        $this->line("[{$timestamp}] Photo #{$photoId}: {$status} {$matched}/{$brands} brands matched ({$rate}%)");
    }
}
