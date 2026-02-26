<?php

namespace App\Console\Commands\Tags;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedObjects extends Command
{
    protected $signature = 'olm:cleanup-orphaned-objects {--fix : Actually delete orphaned rows}';

    protected $description = 'Detect (and optionally delete) orphaned litter objects, CLO rows, and dangling extra_tags';

    public function handle(): int
    {
        $fix = $this->option('fix');

        $this->info('Checking for orphaned tag data...');
        $this->newLine();

        $totalOrphans = 0;

        // 1. litter_objects not referenced by any category_litter_object row
        $orphanedObjects = DB::table('litter_objects')
            ->leftJoin('category_litter_object', 'litter_objects.id', '=', 'category_litter_object.litter_object_id')
            ->whereNull('category_litter_object.id')
            ->pluck('litter_objects.id', 'litter_objects.key');

        $count = $orphanedObjects->count();
        $totalOrphans += $count;
        $this->line("  Orphaned litter_objects (no CLO reference): {$count}");

        if ($fix && $count > 0) {
            DB::table('litter_objects')->whereIn('id', $orphanedObjects->values())->delete();
            $this->info("    Deleted {$count} orphaned litter_objects");
        }

        // 2. category_litter_object rows not referenced by any photo_tags
        $orphanedClos = DB::table('category_litter_object')
            ->leftJoin('photo_tags', 'category_litter_object.id', '=', 'photo_tags.category_litter_object_id')
            ->whereNull('photo_tags.id')
            ->count();

        $this->line("  Unreferenced CLO rows (no photo_tags): {$orphanedClos}");
        // Don't auto-delete CLO rows — they may be needed for future tagging

        // 3. photo_tag_extra_tags with dangling tag_type_id
        $danglingMaterials = DB::table('photo_tag_extra_tags')
            ->where('tag_type', 'material')
            ->leftJoin('materials', 'photo_tag_extra_tags.tag_type_id', '=', 'materials.id')
            ->whereNull('materials.id')
            ->count();

        $danglingBrands = DB::table('photo_tag_extra_tags')
            ->where('tag_type', 'brand')
            ->leftJoin('brandslist', 'photo_tag_extra_tags.tag_type_id', '=', 'brandslist.id')
            ->whereNull('brandslist.id')
            ->count();

        $danglingCustomTags = DB::table('photo_tag_extra_tags')
            ->where('tag_type', 'custom_tag')
            ->leftJoin('custom_tags_new', 'photo_tag_extra_tags.tag_type_id', '=', 'custom_tags_new.id')
            ->whereNull('custom_tags_new.id')
            ->count();

        $danglingTotal = $danglingMaterials + $danglingBrands + $danglingCustomTags;
        $totalOrphans += $danglingTotal;
        $this->line("  Dangling extra_tags (materials: {$danglingMaterials}, brands: {$danglingBrands}, custom: {$danglingCustomTags})");

        if ($fix && $danglingTotal > 0) {
            $deleted = DB::table('photo_tag_extra_tags')
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('tag_type', 'material')
                            ->whereNotIn('tag_type_id', DB::table('materials')->select('id'));
                    })->orWhere(function ($q) {
                        $q->where('tag_type', 'brand')
                            ->whereNotIn('tag_type_id', DB::table('brandslist')->select('id'));
                    })->orWhere(function ($q) {
                        $q->where('tag_type', 'custom_tag')
                            ->whereNotIn('tag_type_id', DB::table('custom_tags_new')->select('id'));
                    });
                })
                ->delete();

            $this->info("    Deleted {$deleted} dangling extra_tags");
        }

        // 4. photo_tag_extra_tags with orphaned photo_tag_id
        $orphanedExtras = DB::table('photo_tag_extra_tags')
            ->leftJoin('photo_tags', 'photo_tag_extra_tags.photo_tag_id', '=', 'photo_tags.id')
            ->whereNull('photo_tags.id')
            ->count();

        $totalOrphans += $orphanedExtras;
        $this->line("  Orphaned extra_tags (missing photo_tag): {$orphanedExtras}");

        if ($fix && $orphanedExtras > 0) {
            $deleted = DB::table('photo_tag_extra_tags')
                ->whereNotIn('photo_tag_id', DB::table('photo_tags')->select('id'))
                ->delete();
            $this->info("    Deleted {$deleted} orphaned extra_tags");
        }

        $this->newLine();

        if ($totalOrphans === 0) {
            $this->info('No orphaned data found.');
        } else {
            $this->warn("Total orphaned items: {$totalOrphans}");
            if (!$fix) {
                $this->line('Run with --fix to delete orphaned data.');
            }
        }

        return self::SUCCESS;
    }
}
