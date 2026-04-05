<?php

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOrphanedTags extends Command
{
    protected $signature = 'olm:fix-orphaned-tags
        {--apply : Actually execute the updates (dry-run by default)}
        {--verify-only : Run post-apply verification queries only}
        {--batch=5000 : Batch size for chunked updates}
        {--log= : Write output to log file (e.g. storage/logs/orphan-fix.log)}';

    protected $description = 'Fix 189k+ orphaned photo_tags from v5 migration (missing category_litter_object_id)';

    private int $totalUpdated = 0;
    private int $totalExpected = 0;
    private array $results = [];
    private bool $apply = false;
    private int $batchSize = 5000;

    /** @var resource|null */
    private $logFile = null;

    /** @var array<int> */
    private array $affectedPhotoIds = [];

    public function handle(): int
    {
        $this->apply = $this->option('apply');
        $this->batchSize = (int) $this->option('batch');

        $this->openLog();

        if ($this->option('verify-only')) {
            return $this->runVerification();
        }

        $mode = $this->apply ? '🔴 LIVE MODE' : '🟢 DRY-RUN MODE';
        $this->log("=== Fix Orphaned Photo Tags ({$mode}) ===");
        $this->log('');

        // Pre-flight: check for existing non-NULL type_ids on orphaned rows
        $existingTypes = DB::table('photo_tags')
            ->whereNull('category_litter_object_id')
            ->whereNotNull('litter_object_id')
            ->whereNotNull('litter_object_type_id')
            ->count();

        $this->log("Pre-flight: orphaned rows with existing litter_object_type_id = {$existingTypes}");
        if ($existingTypes > 0) {
            $this->log("WARNING: {$existingTypes} orphaned rows already have a type_id set. These will NOT be overwritten.", 'warn');
        }
        $this->log('');

        $mappings = $this->buildMappings();

        foreach ($mappings as $mapping) {
            $this->processMapping($mapping);
        }

        $this->log('');
        $this->log('=== SUMMARY ===');
        $this->table(
            ['Orphan Key', 'Category Filter', 'Expected', 'Actual', 'Match?'],
            collect($this->results)->map(fn ($r) => [
                $r['key'],
                $r['category_filter'] ?? '—',
                $r['expected'],
                $r['actual'],
                $r['expected'] === $r['actual'] ? '✓' : '✗ MISMATCH',
            ])->toArray()
        );

        $mismatches = collect($this->results)->filter(fn ($r) => $r['expected'] !== $r['actual']);
        if ($mismatches->isNotEmpty()) {
            $this->log("⚠ {$mismatches->count()} mapping(s) had count mismatches — review above.", 'warn');
        }

        $this->log('');
        $this->log("Total expected: {$this->totalExpected}");
        $this->log("Total " . ($this->apply ? 'updated' : 'would update') . ": {$this->totalUpdated}");

        $uniquePhotos = count(array_unique($this->affectedPhotoIds));
        $this->log("Distinct affected photos: {$uniquePhotos}");

        // Post-flight verification
        if ($this->apply) {
            $this->log('');
            $this->runVerification();
        }

        $this->closeLog();

        return 0;
    }

    private function runVerification(): int
    {
        $this->log('=== VERIFICATION ===');

        $orphanedWithLo = DB::table('photo_tags')
            ->whereNull('category_litter_object_id')
            ->whereNotNull('litter_object_id')
            ->count();
        $this->log("Orphaned photo_tags (NULL CLO + non-NULL LO): {$orphanedWithLo}" . ($orphanedWithLo === 0 ? ' ✓' : ' ✗'));

        $extraTagOnly = DB::table('photo_tags')
            ->whereNull('category_litter_object_id')
            ->whereNull('litter_object_id')
            ->count();
        $this->log("Extra-tag-only (NULL CLO + NULL LO, expected ~24,628): {$extraTagOnly}");

        $spotCheck = DB::table('photo_tags')
            ->join('litter_objects', 'photo_tags.litter_object_id', '=', 'litter_objects.id')
            ->whereNull('photo_tags.category_litter_object_id')
            ->whereIn('litter_objects.key', ['energy_can', 'beer_can', 'water_bottle', 'soda_can'])
            ->selectRaw('litter_objects.`key`, COUNT(*) as remaining')
            ->groupBy('litter_objects.key')
            ->get();

        if ($spotCheck->isEmpty()) {
            $this->log('Spot check (energy_can, beer_can, water_bottle, soda_can): 0 remaining ✓');
        } else {
            foreach ($spotCheck as $row) {
                $this->log("Spot check: {$row->key} still has {$row->remaining} orphaned rows ✗", 'warn');
            }
        }

        $this->closeLog();

        return 0;
    }

    private function processMapping(array $mapping): void
    {
        $label = $mapping['label'];
        $orphanLoId = $mapping['orphan_lo_id'];
        $targetCloId = $mapping['target_clo_id'];
        $targetLoId = $mapping['target_lo_id'];
        $targetCategoryId = $mapping['target_category_id'];
        $typeId = $mapping['type_id'] ?? null;
        $categoryFilter = $mapping['category_filter'] ?? null;

        $query = DB::table('photo_tags')
            ->where('litter_object_id', $orphanLoId)
            ->whereNull('category_litter_object_id');

        if ($categoryFilter !== null) {
            $query->where('category_id', $categoryFilter);
        }

        $count = $query->count();

        // Sample up to 5 affected photo_ids for spot-checking
        $samplePhotoIds = (clone $query)->limit(5)->pluck('photo_id');
        if ($samplePhotoIds->isNotEmpty()) {
            $this->log("    Sample photo_ids: " . $samplePhotoIds->join(', '));
        }

        // Collect all affected photo_ids for the summary count
        $allPhotoIds = (clone $query)->pluck('photo_id')->unique()->values()->toArray();
        $this->affectedPhotoIds = array_merge($this->affectedPhotoIds, $allPhotoIds);

        $updateData = [
            'category_litter_object_id' => $targetCloId,
            'litter_object_id' => $targetLoId,
            'category_id' => $targetCategoryId,
        ];

        $categoryLabel = $categoryFilter !== null ? " (cat={$categoryFilter})" : '';
        $typeLabel = $typeId !== null ? " +type={$typeId}" : '';

        if ($this->apply && $count > 0) {
            $updated = $this->executeBatched($orphanLoId, $categoryFilter, $updateData, $typeId);
        } else {
            $updated = $count;
        }

        $this->results[] = [
            'key' => $label,
            'category_filter' => $categoryFilter,
            'expected' => $count,
            'actual' => $updated,
        ];

        $this->totalExpected += $count;
        $this->totalUpdated += $updated;

        $action = $this->apply ? 'Updated' : 'Would update';
        $this->log("  {$action} {$updated}/{$count} — {$label}{$categoryLabel}{$typeLabel} → CLO {$targetCloId}, LO {$targetLoId}");
    }

    private function executeBatched(int $orphanLoId, ?int $categoryFilter, array $updateData, ?int $typeId): int
    {
        $totalUpdated = 0;

        DB::transaction(function () use ($orphanLoId, $categoryFilter, $updateData, $typeId, &$totalUpdated) {
            while (true) {
                $query = DB::table('photo_tags')
                    ->where('litter_object_id', $orphanLoId)
                    ->whereNull('category_litter_object_id');

                if ($categoryFilter !== null) {
                    $query->where('category_id', $categoryFilter);
                }

                $ids = $query->limit($this->batchSize)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                // Main update: CLO, LO, category
                DB::table('photo_tags')
                    ->whereIn('id', $ids)
                    ->update($updateData);

                // Type update: only set on rows that don't already have a type_id
                if ($typeId !== null) {
                    DB::table('photo_tags')
                        ->whereIn('id', $ids)
                        ->whereNull('litter_object_type_id')
                        ->update(['litter_object_type_id' => $typeId]);
                }

                $totalUpdated += $ids->count();
                $this->log("    Batch: {$ids->count()} rows (running total: {$totalUpdated})");
            }
        });

        return $totalUpdated;
    }

    /**
     * Build the complete mapping array from the diagnostic.
     *
     * Each entry: orphan LO ID → target CLO ID, canonical LO ID, category ID, optional type ID.
     * Multi-category orphans have separate entries with category_filter.
     */
    private function buildMappings(): array
    {
        return [
            // ── Alcohol ──
            ['label' => 'beer_can', 'orphan_lo_id' => 137, 'target_clo_id' => 5, 'target_lo_id' => 5, 'target_category_id' => 2, 'type_id' => 1],
            ['label' => 'beer_bottle', 'orphan_lo_id' => 138, 'target_clo_id' => 2, 'target_lo_id' => 2, 'target_category_id' => 2, 'type_id' => 1],
            ['label' => 'spirits_bottle', 'orphan_lo_id' => 144, 'target_clo_id' => 2, 'target_lo_id' => 2, 'target_category_id' => 2, 'type_id' => 3],
            ['label' => 'wine_bottle', 'orphan_lo_id' => 139, 'target_clo_id' => 2, 'target_lo_id' => 2, 'target_category_id' => 2, 'type_id' => 2],
            ['label' => 'bottletops', 'orphan_lo_id' => 146, 'target_clo_id' => 4, 'target_lo_id' => 4, 'target_category_id' => 2],

            // ── Alcohol / Softdrinks split: brokenglass ──
            ['label' => 'brokenglass', 'orphan_lo_id' => 164, 'target_clo_id' => 3, 'target_lo_id' => 3, 'target_category_id' => 2, 'category_filter' => 2],
            ['label' => 'brokenglass', 'orphan_lo_id' => 164, 'target_clo_id' => 151, 'target_lo_id' => 3, 'target_category_id' => 16, 'category_filter' => 16],

            // ── Softdrinks ──
            ['label' => 'energy_can', 'orphan_lo_id' => 156, 'target_clo_id' => 152, 'target_lo_id' => 5, 'target_category_id' => 16, 'type_id' => 26],
            ['label' => 'water_bottle', 'orphan_lo_id' => 140, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 23],
            ['label' => 'soda_can', 'orphan_lo_id' => 142, 'target_clo_id' => 152, 'target_lo_id' => 5, 'target_category_id' => 16, 'type_id' => 24],
            ['label' => 'fizzy_bottle', 'orphan_lo_id' => 145, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 24],
            ['label' => 'sports_bottle', 'orphan_lo_id' => 143, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 27],
            ['label' => 'juice_carton', 'orphan_lo_id' => 154, 'target_clo_id' => 153, 'target_lo_id' => 124, 'target_category_id' => 16, 'type_id' => 25],
            ['label' => 'juice_bottle', 'orphan_lo_id' => 155, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 25],
            ['label' => 'straw_packaging', 'orphan_lo_id' => 172, 'target_clo_id' => 162, 'target_lo_id' => 126, 'target_category_id' => 16],
            ['label' => 'milk_bottle', 'orphan_lo_id' => 153, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 29],
            ['label' => 'iceTea_bottle', 'orphan_lo_id' => 186, 'target_clo_id' => 149, 'target_lo_id' => 2, 'target_category_id' => 16, 'type_id' => 28],
            ['label' => 'milk_carton', 'orphan_lo_id' => 151, 'target_clo_id' => 153, 'target_lo_id' => 124, 'target_category_id' => 16, 'type_id' => 29],
            ['label' => 'pullRing', 'orphan_lo_id' => 175, 'target_clo_id' => 160, 'target_lo_id' => 10, 'target_category_id' => 16],
            ['label' => 'icedTea_can', 'orphan_lo_id' => 194, 'target_clo_id' => 152, 'target_lo_id' => 5, 'target_category_id' => 16, 'type_id' => 31],

            // ── Softdrinks / Marine split: straws ──
            ['label' => 'straws', 'orphan_lo_id' => 150, 'target_clo_id' => 161, 'target_lo_id' => 25, 'target_category_id' => 16, 'category_filter' => 16],
            ['label' => 'straws', 'orphan_lo_id' => 150, 'target_clo_id' => 93, 'target_lo_id' => 1, 'target_category_id' => 10, 'category_filter' => 10],

            // ── Smoking ──
            ['label' => 'cigarette_box', 'orphan_lo_id' => 141, 'target_clo_id' => 140, 'target_lo_id' => 37, 'target_category_id' => 15, 'type_id' => 13],
            ['label' => 'rollingPapers', 'orphan_lo_id' => 148, 'target_clo_id' => 144, 'target_lo_id' => 120, 'target_category_id' => 15],
            ['label' => 'filters', 'orphan_lo_id' => 167, 'target_clo_id' => 146, 'target_lo_id' => 122, 'target_category_id' => 15],
            ['label' => 'vapePen', 'orphan_lo_id' => 190, 'target_clo_id' => 147, 'target_lo_id' => 123, 'target_category_id' => 15, 'type_id' => 17],
            ['label' => 'tobaccopouch', 'orphan_lo_id' => 162, 'target_clo_id' => 145, 'target_lo_id' => 121, 'target_category_id' => 15, 'type_id' => 15],
            ['label' => 'vapeOil', 'orphan_lo_id' => 189, 'target_clo_id' => 147, 'target_lo_id' => 123, 'target_category_id' => 15, 'type_id' => 22],

            // ── Sanitary → Medical (category change) ──
            ['label' => 'facemask', 'orphan_lo_id' => 183, 'target_clo_id' => 95, 'target_lo_id' => 79, 'target_category_id' => 11],
            ['label' => 'gloves', 'orphan_lo_id' => 80, 'target_clo_id' => 96, 'target_lo_id' => 80, 'target_category_id' => 11],
            ['label' => 'sanitiser', 'orphan_lo_id' => 85, 'target_clo_id' => 101, 'target_lo_id' => 85, 'target_category_id' => 11],

            // ── Sanitary (same category) ──
            ['label' => 'wetwipes', 'orphan_lo_id' => 179, 'target_clo_id' => 125, 'target_lo_id' => 104, 'target_category_id' => 14],
            ['label' => 'earSwabs', 'orphan_lo_id' => 166, 'target_clo_id' => 127, 'target_lo_id' => 106, 'target_category_id' => 14],
            ['label' => 'condoms', 'orphan_lo_id' => 158, 'target_clo_id' => 136, 'target_lo_id' => 115, 'target_category_id' => 14],
            ['label' => 'menstrual', 'orphan_lo_id' => 182, 'target_clo_id' => 133, 'target_lo_id' => 112, 'target_category_id' => 14],
            ['label' => 'toothpick', 'orphan_lo_id' => 168, 'target_clo_id' => 138, 'target_lo_id' => 1, 'target_category_id' => 14],
            ['label' => 'hair_tie', 'orphan_lo_id' => 160, 'target_clo_id' => 138, 'target_lo_id' => 1, 'target_category_id' => 14],
            ['label' => 'ear_plugs', 'orphan_lo_id' => 161, 'target_clo_id' => 138, 'target_lo_id' => 1, 'target_category_id' => 14],

            // ── Food ──
            ['label' => 'crisp_small', 'orphan_lo_id' => 157, 'target_clo_id' => 49, 'target_lo_id' => 40, 'target_category_id' => 8],
            ['label' => 'crisp_large', 'orphan_lo_id' => 163, 'target_clo_id' => 49, 'target_lo_id' => 40, 'target_category_id' => 8],
            ['label' => 'glass_jar', 'orphan_lo_id' => 159, 'target_clo_id' => 52, 'target_lo_id' => 43, 'target_category_id' => 8],

            // ── Other → correct category (category changes) ──
            ['label' => 'dump', 'orphan_lo_id' => 165, 'target_clo_id' => 34, 'target_lo_id' => 28, 'target_category_id' => 6],
            ['label' => 'dogshit', 'orphan_lo_id' => 102, 'target_clo_id' => 122, 'target_lo_id' => 102, 'target_category_id' => 13],
            ['label' => 'dogshit_in_bag', 'orphan_lo_id' => 103, 'target_clo_id' => 123, 'target_lo_id' => 103, 'target_category_id' => 13],
            ['label' => 'batteries', 'orphan_lo_id' => 181, 'target_clo_id' => 36, 'target_lo_id' => 29, 'target_category_id' => 7],
            ['label' => 'tyre', 'orphan_lo_id' => 135, 'target_clo_id' => 173, 'target_lo_id' => 135, 'target_category_id' => 17],
            ['label' => 'life_buoy', 'orphan_lo_id' => 184, 'target_clo_id' => 78, 'target_lo_id' => 63, 'target_category_id' => 10],

            // ── Other (same category) ──
            ['label' => 'randomLitter', 'orphan_lo_id' => 170, 'target_clo_id' => 121, 'target_lo_id' => 1, 'target_category_id' => 12],
            ['label' => 'plasticBags', 'orphan_lo_id' => 149, 'target_clo_id' => 111, 'target_lo_id' => 92, 'target_category_id' => 12],
            ['label' => 'bagsLitter', 'orphan_lo_id' => 176, 'target_clo_id' => 106, 'target_lo_id' => 15, 'target_category_id' => 12],
            ['label' => 'cableTie', 'orphan_lo_id' => 187, 'target_clo_id' => 114, 'target_lo_id' => 95, 'target_category_id' => 12],
            ['label' => 'overflowingBins', 'orphan_lo_id' => 188, 'target_clo_id' => 107, 'target_lo_id' => 19, 'target_category_id' => 12],
            ['label' => 'trafficCone', 'orphan_lo_id' => 171, 'target_clo_id' => 109, 'target_lo_id' => 90, 'target_category_id' => 12],
            ['label' => 'posters', 'orphan_lo_id' => 177, 'target_clo_id' => 113, 'target_lo_id' => 94, 'target_category_id' => 12],
            ['label' => 'washingUp', 'orphan_lo_id' => 152, 'target_clo_id' => 121, 'target_lo_id' => 1, 'target_category_id' => 12],
            ['label' => 'magazine', 'orphan_lo_id' => 191, 'target_clo_id' => 121, 'target_lo_id' => 1, 'target_category_id' => 12],
            ['label' => 'books', 'orphan_lo_id' => 192, 'target_clo_id' => 121, 'target_lo_id' => 1, 'target_category_id' => 12],
            ['label' => 'lego', 'orphan_lo_id' => 197, 'target_clo_id' => 121, 'target_lo_id' => 1, 'target_category_id' => 12],
            ['label' => 'automobile', 'orphan_lo_id' => 180, 'target_clo_id' => 167, 'target_lo_id' => 129, 'target_category_id' => 17],
            ['label' => 'elec_small', 'orphan_lo_id' => 174, 'target_clo_id' => 43, 'target_lo_id' => 1, 'target_category_id' => 7],
            ['label' => 'elec_large', 'orphan_lo_id' => 169, 'target_clo_id' => 43, 'target_lo_id' => 1, 'target_category_id' => 7],

            // ── Other / Marine split: balloons ──
            ['label' => 'balloons', 'orphan_lo_id' => 178, 'target_clo_id' => 115, 'target_lo_id' => 96, 'target_category_id' => 12, 'category_filter' => 12],
            ['label' => 'balloons', 'orphan_lo_id' => 178, 'target_clo_id' => 93, 'target_lo_id' => 1, 'target_category_id' => 10, 'category_filter' => 10],

            // ── Marine ──
            ['label' => 'mediumplastics', 'orphan_lo_id' => 147, 'target_clo_id' => 85, 'target_lo_id' => 70, 'target_category_id' => 10],
            ['label' => 'bag (marine)', 'orphan_lo_id' => 36, 'target_clo_id' => 93, 'target_lo_id' => 1, 'target_category_id' => 10],
            ['label' => 'bottle (marine)', 'orphan_lo_id' => 2, 'target_clo_id' => 93, 'target_lo_id' => 1, 'target_category_id' => 10, 'category_filter' => 10],
            ['label' => 'fishing_nets', 'orphan_lo_id' => 173, 'target_clo_id' => 84, 'target_lo_id' => 69, 'target_category_id' => 10],
            ['label' => 'lighters (marine)', 'orphan_lo_id' => 119, 'target_clo_id' => 142, 'target_lo_id' => 119, 'target_category_id' => 15, 'category_filter' => 10],
            ['label' => 'shotgun_cartridges', 'orphan_lo_id' => 193, 'target_clo_id' => 91, 'target_lo_id' => 76, 'target_category_id' => 10],
            ['label' => 'buoys', 'orphan_lo_id' => 196, 'target_clo_id' => 78, 'target_lo_id' => 63, 'target_category_id' => 10],

            // ── Industrial ──
            ['label' => 'plastic (industrial)', 'orphan_lo_id' => 89, 'target_clo_id' => 108, 'target_lo_id' => 89, 'target_category_id' => 12, 'category_filter' => 9],
            ['label' => 'oil', 'orphan_lo_id' => 198, 'target_clo_id' => 67, 'target_lo_id' => 54, 'target_category_id' => 9],
            ['label' => 'chemical', 'orphan_lo_id' => 195, 'target_clo_id' => 69, 'target_lo_id' => 56, 'target_category_id' => 9],

            // ── Art ──
            ['label' => 'item (art)', 'orphan_lo_id' => 185, 'target_clo_id' => 16, 'target_lo_id' => 1, 'target_category_id' => 3],
        ];
    }

    private function log(string $message, string $level = 'info'): void
    {
        match ($level) {
            'warn' => $this->warn($message),
            'error' => $this->error($message),
            default => $message === '' ? $this->newLine() : $this->info($message),
        };

        if ($this->logFile) {
            fwrite($this->logFile, '[' . now()->toDateTimeString() . "] {$message}\n");
        }
    }

    private function openLog(): void
    {
        $path = $this->option('log');

        if (! $path) {
            return;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->logFile = fopen($path, 'a');

        if ($this->logFile) {
            $this->info("Logging to: {$path}");
            fwrite($this->logFile, "\n=== " . now()->toDateTimeString() . " ===\n");
        }
    }

    private function closeLog(): void
    {
        if ($this->logFile) {
            fclose($this->logFile);
            $this->logFile = null;
        }
    }
}
