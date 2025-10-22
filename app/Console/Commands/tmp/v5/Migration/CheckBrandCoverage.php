<?php

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckBrandCoverage extends Command
{
    protected $signature = 'olm:v5:check-coverage';

    protected $description = 'Check brand relationship coverage before migration';

    public function handle()
    {
        $this->info('Brand Relationship Coverage Check');
        $this->info('=================================');

        // Get top brands from photos
        $topBrands = DB::table('custom_tags')
            ->selectRaw("LOWER(REPLACE(SUBSTRING(tag, 7), ' ', '_')) as brand, COUNT(*) as photos")
            ->where('tag', 'like', 'brand:%')
            ->groupBy('brand')
            ->orderByDesc('photos')
            ->limit(100)
            ->get();

        $hasRelationship = 0;
        $noRelationship = 0;
        $photosCovered = 0;
        $photosUncovered = 0;

        $this->info("\nTop 100 Brands:");
        $this->info("---------------");

        foreach ($topBrands as $brand) {
            // Check if brand has relationships
            $brandId = DB::table('brandslist')->where('key', $brand->brand)->value('id');
            $relationships = 0;

            if ($brandId) {
                $relationships = DB::table('taggables')
                    ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                    ->where('taggable_id', $brandId)
                    ->count();
            }

            $status = $relationships > 0 ? '✓' : '✗';

            if ($relationships > 0) {
                $hasRelationship++;
                $photosCovered += $brand->photos;

                // Get the relationship
                $rel = DB::table('taggables')
                    ->join('category_litter_object', 'taggables.category_litter_object_id', '=', 'category_litter_object.id')
                    ->join('categories', 'category_litter_object.category_id', '=', 'categories.id')
                    ->join('litter_objects', 'category_litter_object.litter_object_id', '=', 'litter_objects.id')
                    ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                    ->where('taggables.taggable_id', $brandId)
                    ->select('categories.key as category', 'litter_objects.key as object')
                    ->first();

                $this->line(sprintf(
                    "%s %-20s → %-15s.%-15s (%d photos)",
                    $status,
                    $brand->brand,
                    $rel->category,
                    $rel->object,
                    $brand->photos
                ));
            } else {
                $noRelationship++;
                $photosUncovered += $brand->photos;

                $this->line(sprintf(
                    "%s %-20s   [NO RELATIONSHIP]              (%d photos)",
                    $status,
                    $brand->brand,
                    $brand->photos
                ));
            }
        }

        $totalPhotos = $photosCovered + $photosUncovered;
        $coveragePercent = $totalPhotos > 0 ? round($photosCovered / $totalPhotos * 100, 1) : 0;

        $this->info("\n" . str_repeat("=", 50));
        $this->info("Summary:");
        $this->info("--------");
        $this->info("Brands with relationships: {$hasRelationship}/100");
        $this->info("Brands without: {$noRelationship}/100");
        $this->info("Photo coverage: {$photosCovered}/{$totalPhotos} ({$coveragePercent}%)");

        if ($coveragePercent < 80) {
            $this->warn("\n⚠️  Coverage is below 80%. Run:");
            $this->warn("php artisan olm:v5:define-relationships --top=500 --export");
        } else {
            $this->info("\n✅ Good coverage! Ready for migration.");
        }
    }
}
