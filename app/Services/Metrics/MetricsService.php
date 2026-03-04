<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Enums\LocationType;
use App\Enums\XpScore;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Facades\{DB, Log, Redis};

/**
 * Metrics system for OpenLitterMap v5
 *
 * - MySQL metrics table is the source of truth for all time-series
 * - Redis contains derived aggregates & rankings (rebuildable)
 * - Operations are idempotent via fingerprinting
 * - Deltas: create +1 upload, update 0 uploads, delete -1 upload
 * - Tags count = objects + materials + brands (not categories to avoid double-counting)
 * - XP is reversible on updates/deletes
 * - All timestamps in UTC, ISO weeks use ISO year
 * - Row locking prevents concurrent processing
 */
final class MetricsService
{
    /**
     * Process a photo with row locking to prevent concurrency issues
     */
    public function processPhoto(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            // Lock the row to prevent concurrent processing
            $photo = Photo::whereKey($photo->id)->lockForUpdate()->first();

            if (!$photo) {
                return;
            }

            $metrics = $this->extractMetricsFromPhoto($photo);
            $fingerprint = $this->computeFingerprint($metrics['tags']);

            // FIX #1: Check both fingerprint AND XP to detect changes
            if ($photo->processed_fp === $fingerprint &&
                (int)$photo->processed_xp === (int)$metrics['xp']) {
                return; // Nothing changed
            }

            // Route to appropriate handler within the transaction
            if ($photo->processed_at !== null) {
                $this->doUpdate($photo, $metrics, $fingerprint);
            } else {
                $this->doCreate($photo, $metrics, $fingerprint);
            }
        });
    }

    /**
     * Delete a photo's metrics (reversing XP as well)
     */
    public function deletePhoto(Photo $photo): void
    {
        DB::transaction(function () use ($photo) {
            // Lock the row
            $photo = Photo::whereKey($photo->id)->lockForUpdate()->first();

            if (!$photo || $photo->processed_at === null) {
                return;
            }

            $oldTags = json_decode($photo->processed_tags ?? '{}', true);
            $oldXp = (int)($photo->processed_xp ?? 0);

            // Calculate negative metrics from stored values
            $negativeMetrics = [
                'tags_count' => -(
                    array_sum($oldTags['objects'] ?? []) +
                    array_sum($oldTags['materials'] ?? []) +
                    array_sum($oldTags['brands'] ?? [])
                ),
                'brands_count' => -array_sum($oldTags['brands'] ?? []),
                'materials_count' => -array_sum($oldTags['materials'] ?? []),
                'custom_tags_count' => -array_sum($oldTags['custom_tags'] ?? []),
                'litter' => -array_sum($oldTags['objects'] ?? []),
                'xp' => -$oldXp,
            ];

            // Apply negative deltas with -1 upload
            $rows = $this->buildTimeSeriesRows($photo, $negativeMetrics, -1);
            $this->upsertTimeSeriesRows($rows); // GREATEST prevents going negative

            // Clear processing data
            $photo->update([
                'processed_at' => null,
                'processed_fp' => null,
                'processed_tags' => null,
                'processed_xp' => null,
            ]);

            // Reverse users.xp — processed_xp includes upload base + tag XP
            User::where('id', $photo->user_id)
                ->update(['xp' => DB::raw("GREATEST(CAST(xp AS SIGNED) - {$oldXp}, 0)")]);

            // FIX #3: Use unified Redis update (pass positive values, collector will negate)
            $this->updateRedis($photo, [
                'tags' => $oldTags,
                'litter' => array_sum($oldTags['objects'] ?? []),
                'xp' => $oldXp,
            ], 'delete');
        });
    }

    /**
     * Record upload-time metrics (XP + 1 upload) so the user appears
     * on time-filtered leaderboards immediately, before tagging.
     * When MetricsService::processPhoto() runs later at tag time,
     * it will upsert over these rows with the full XP (which already
     * includes the upload base via XpCalculator).
     */
    public function recordUploadMetrics(Photo $photo, int $uploadXp): void
    {
        $metrics = [
            'tags_count' => 0,
            'brands_count' => 0,
            'materials_count' => 0,
            'custom_tags_count' => 0,
            'litter' => 0,
            'xp' => $uploadXp,
        ];

        // Write to MySQL metrics table (upload appears on time-filtered leaderboards)
        $rows = $this->buildTimeSeriesRows($photo, $metrics, 1);
        $this->upsertTimeSeriesRows($rows);

        // Mark photo as "processed" with upload-only metrics so processPhoto()
        // at tag time routes to doUpdate() (delta-based) instead of doCreate()
        // which would double-count uploads and XP.
        $photo->update([
            'processed_at' => now('UTC'),
            'processed_fp' => '',
            'processed_tags' => json_encode([], JSON_NUMERIC_CHECK),
            'processed_xp' => $uploadXp,
        ]);

        // Update Redis: user stats, leaderboard ZSETs, scope stats, HLLs.
        // Called directly (not via DB::afterCommit) because recordUploadMetrics
        // is not wrapped in DB::transaction — afterCommit callbacks at the
        // outermost transaction level would not fire in RefreshDatabase tests.
        RedisMetricsCollector::processPhoto($photo, [
            'tags' => [],
            'litter' => 0,
            'xp' => $uploadXp,
        ], 'create');
    }

    /**
     * Create new photo within transaction
     */
    private function doCreate(Photo $photo, array $metrics, string $fingerprint): void
    {
        // Use upsert for idempotency (handles retries)
        $rows = $this->buildTimeSeriesRows($photo, $metrics, 1);
        $this->upsertTimeSeriesRows($rows);

        // Mark as processed
        $photo->update([
            'processed_at' => now('UTC'),
            'processed_fp' => $fingerprint,
            'processed_tags' => json_encode($metrics['tags'], JSON_NUMERIC_CHECK),
            'processed_xp' => $metrics['xp'],
        ]);

        $this->updateRedis($photo, $metrics, 'create');
    }

    /**
     * Update existing photo within transaction
     */
    private function doUpdate(Photo $photo, array $newMetrics, string $newFingerprint): void
    {
        $oldTags = json_decode($photo->processed_tags ?? '{}', true);
        $oldXp = (int)($photo->processed_xp ?? 0);

        // Calculate deltas
        $tagDeltas = $this->calculateTagDeltas($oldTags, $newMetrics['tags']);
        $xpDelta = $newMetrics['xp'] - $oldXp;

        // Check if anything actually changed
        if ($this->isDeltaEmpty($tagDeltas) && $xpDelta === 0) {
            // Still update fingerprint and XP tracking even if no deltas
            $photo->update([
                'processed_fp' => $newFingerprint,
                'processed_xp' => $newMetrics['xp'],
            ]);
            return;
        }

        // Build delta metrics
        $deltaMetrics = [
            'tags_count' => (
                array_sum($tagDeltas['objects'] ?? []) +
                array_sum($tagDeltas['materials'] ?? []) +
                array_sum($tagDeltas['brands'] ?? [])
            ),
            'brands_count' => array_sum($tagDeltas['brands'] ?? []),
            'materials_count' => array_sum($tagDeltas['materials'] ?? []),
            'custom_tags_count' => array_sum($tagDeltas['custom_tags'] ?? []),
            'litter' => array_sum($tagDeltas['objects'] ?? []),
            'xp' => $xpDelta,
        ];

        // Apply deltas (0 uploads for updates)
        $rows = $this->buildTimeSeriesRows($photo, $deltaMetrics, 0);
        $this->upsertTimeSeriesRows($rows);

        // Update photo record
        $photo->update([
            'processed_fp' => $newFingerprint,
            'processed_tags' => json_encode($newMetrics['tags'], JSON_NUMERIC_CHECK),
            'processed_xp' => $newMetrics['xp'],
        ]);

        // Sync users.xp with tag XP delta (upload XP cancels out in delta).
        // Use GREATEST to prevent unsigned underflow on users.xp column.
        if ($xpDelta !== 0) {
            User::where('id', $photo->user_id)
                ->update(['xp' => DB::raw("GREATEST(CAST(xp AS SIGNED) + {$xpDelta}, 0)")]);
        }

        $this->updateRedis($photo, [
            'tags' => $tagDeltas,
            'litter' => $deltaMetrics['litter'],
            'xp' => $xpDelta,
        ], 'update');
    }

    private function updateRedis(Photo $photo, array $payload, string $operation): void
    {
        DB::afterCommit(function() use ($photo, $payload, $operation) {
            RedisMetricsCollector::processPhoto($photo, $payload, $operation);
        });
    }

    /**
     * Extract metrics from photo summary.
     * Supports both flat array (v5.1) and nested dict (v5.0) formats.
     */
    private function extractMetricsFromPhoto(Photo $photo): array
    {
        $summary = $photo->summary ?? [];
        $summaryTags = $summary['tags'] ?? [];

        // Detect format: flat array (list) vs nested dict (associative)
        if (is_array($summaryTags) && array_is_list($summaryTags)) {
            $metrics = $this->extractFromFlatSummary($summaryTags, $photo);
        } else {
            $metrics = $this->extractFromNestedSummary($summaryTags, $photo);
        }

        // photo.xp is tag-only; add upload base so effective XP matches
        // what's stored in processed_xp and metrics table (upload + tag)
        $metrics['xp'] = (int) ($photo->xp ?? 0) + XpScore::Upload->xp();

        return $metrics;
    }

    /**
     * Extract metrics from flat array summary format (v5.1).
     */
    private function extractFromFlatSummary(array $summaryTags, Photo $photo): array
    {
        $tags = ['categories' => [], 'objects' => [], 'materials' => [], 'brands' => [], 'custom_tags' => [], 'types' => []];
        $totalLitter = 0;
        $totalBrands = 0;
        $totalMaterials = 0;
        $totalCustom = 0;

        foreach ($summaryTags as $tag) {
            $quantity = (int) ($tag['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $categoryId = (int) ($tag['category_id'] ?? 0);
            $objectId = (int) ($tag['object_id'] ?? 0);
            $typeId = $tag['type_id'] ?? null;

            // Categories
            if ($categoryId > 0) {
                $tags['categories'][$categoryId] = ($tags['categories'][$categoryId] ?? 0) + $quantity;
            }

            // Objects
            if ($objectId > 0) {
                $tags['objects'][$objectId] = ($tags['objects'][$objectId] ?? 0) + $quantity;
            }

            $totalLitter += $quantity;

            // Types
            if ($typeId) {
                $tags['types'][$typeId] = ($tags['types'][$typeId] ?? 0) + $quantity;
            }

            // Materials — set membership, weighted by parent tag quantity
            foreach ($tag['materials'] ?? [] as $materialId) {
                $tags['materials'][$materialId] = ($tags['materials'][$materialId] ?? 0) + $quantity;
                $totalMaterials += $quantity;
            }

            // Brands — independent quantities
            foreach ((array) ($tag['brands'] ?? []) as $brandId => $brandQty) {
                $brandQty = (int) $brandQty;
                $tags['brands'][$brandId] = ($tags['brands'][$brandId] ?? 0) + $brandQty;
                $totalBrands += $brandQty;
            }

            // Custom tags — set membership, weighted by parent tag quantity
            foreach ($tag['custom_tags'] ?? [] as $customTagId) {
                $tags['custom_tags'][$customTagId] = ($tags['custom_tags'][$customTagId] ?? 0) + $quantity;
                $totalCustom += $quantity;
            }
        }

        // Remove empty types dimension to avoid unnecessary delta computation
        if (empty($tags['types'])) {
            unset($tags['types']);
        }

        $tagsCount = array_sum($tags['objects'] ?? []) +
            array_sum($tags['materials'] ?? []) +
            array_sum($tags['brands'] ?? []) +
            array_sum($tags['custom_tags'] ?? []);

        return [
            'tags' => $tags,
            'tags_count' => $tagsCount,
            'brands_count' => $totalBrands,
            'materials_count' => $totalMaterials,
            'custom_tags_count' => $totalCustom,
            'litter' => $totalLitter,
            'xp' => 0, // placeholder — caller adds upload base
        ];
    }

    /**
     * Extract metrics from nested dict summary format (v5.0 legacy).
     */
    private function extractFromNestedSummary(array $summaryTags, Photo $photo): array
    {
        $tags = ['categories' => [], 'objects' => [], 'materials' => [], 'brands' => [], 'custom_tags' => []];
        $totalLitter = 0;
        $totalBrands = 0;
        $totalMaterials = 0;
        $totalCustom = 0;

        foreach ($summaryTags as $categoryKey => $objects) {
            if (!is_array($objects)) {
                continue;
            }

            $categoryTotal = 0;
            foreach ($objects as $objectKey => $data) {
                if (!is_array($data)) {
                    continue;
                }

                $quantity = (int) ($data['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $objectId = is_numeric($objectKey) ? (int) $objectKey : $objectKey;
                $tags['objects'][$objectId] = ($tags['objects'][$objectId] ?? 0) + $quantity;
                $categoryTotal += $quantity;
                $totalLitter += $quantity;

                foreach ($data['materials'] ?? [] as $key => $count) {
                    $id = is_numeric($key) ? (int) $key : $key;
                    $qty = (int) $count;
                    $tags['materials'][$id] = ($tags['materials'][$id] ?? 0) + $qty;
                    $totalMaterials += $qty;
                }

                foreach ($data['brands'] ?? [] as $key => $count) {
                    $id = is_numeric($key) ? (int) $key : $key;
                    $qty = (int) $count;
                    $tags['brands'][$id] = ($tags['brands'][$id] ?? 0) + $qty;
                    $totalBrands += $qty;
                }

                foreach ($data['custom_tags'] ?? [] as $key => $count) {
                    $id = is_numeric($key) ? (int) $key : $key;
                    $qty = (int) $count;
                    $tags['custom_tags'][$id] = ($tags['custom_tags'][$id] ?? 0) + $qty;
                    $totalCustom += $qty;
                }
            }

            if ($categoryTotal > 0) {
                $categoryId = is_numeric($categoryKey) ? (int) $categoryKey : $categoryKey;
                $tags['categories'][$categoryId] = $categoryTotal;
            }
        }

        $tagsCount = array_sum($tags['objects']) +
            array_sum($tags['materials']) +
            array_sum($tags['brands']) +
            array_sum($tags['custom_tags']);

        return [
            'tags' => $tags,
            'tags_count' => $tagsCount,
            'brands_count' => $totalBrands,
            'materials_count' => $totalMaterials,
            'custom_tags_count' => $totalCustom,
            'litter' => $totalLitter,
            'xp' => 0, // placeholder — caller adds upload base
        ];
    }

    /**
     * Build time-series rows for all timescales and locations
     */
    private function buildTimeSeriesRows(Photo $photo, array $metrics, int $uploadsDelta): array
    {
        $timestamp = $photo->created_at->copy()->utc();
        $locations = $this->getLocationHierarchy($photo);
        $rows = [];

        foreach ($locations as [$locationType, $locationId]) {
            // All timescales: 0=all-time, 1=daily, 2=weekly, 3=monthly, 4=yearly
            foreach ([0, 1, 2, 3, 4] as $timescale) {
                // Aggregate row (user_id=0)
                $rows[] = $this->buildSingleRow($timescale, $locationType, $locationId, $timestamp, $metrics, $uploadsDelta);

                // Per-user row (user_id>0) for leaderboard queries
                $rows[] = $this->buildSingleRow($timescale, $locationType, $locationId, $timestamp, $metrics, $uploadsDelta, $photo->user_id);
            }
        }

        return $rows;
    }

    /**
     * Build a single time-series row
     */
    private function buildSingleRow(
        int $timescale,
        LocationType $locationType,
        int $locationId,
        $timestamp,
        array $metrics,
        int $uploadsDelta,
        int $userId = 0
    ): array {
        $base = [
            'timescale' => $timescale,
            'location_type' => $locationType->value,
            'location_id' => $locationId,
            'user_id' => $userId,
            'uploads' => $uploadsDelta,
            'tags' => $metrics['tags_count'] ?? 0,
            'brands' => $metrics['brands_count'] ?? 0,
            'materials' => $metrics['materials_count'] ?? 0,
            'custom_tags' => $metrics['custom_tags_count'] ?? 0,
            'litter' => $metrics['litter'] ?? 0,
            'xp' => $metrics['xp'] ?? 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ];

        switch ($timescale) {
            case 0: // All-time
                return $base + [
                        'year' => 0,
                        'month' => 0,
                        'week' => 0,
                        'bucket_date' => '1970-01-01',
                    ];

            case 1: // Daily
                return $base + [
                        'year' => $timestamp->year,
                        'month' => $timestamp->month,
                        'week' => (int)$timestamp->format('W'),
                        'bucket_date' => $timestamp->toDateString(),
                    ];

            case 2: // Weekly (ISO)
                $weekStart = $timestamp->copy()->startOfWeek();
                return $base + [
                        'year' => (int)$timestamp->format('o'), // ISO year
                        'month' => $weekStart->month,
                        'week' => (int)$timestamp->format('W'),
                        'bucket_date' => $weekStart->toDateString(),
                    ];

            case 3: // Monthly
                return $base + [
                        'year' => $timestamp->year,
                        'month' => $timestamp->month,
                        'week' => 0,
                        'bucket_date' => $timestamp->copy()->startOfMonth()->toDateString(),
                    ];

            case 4: // Yearly
                return $base + [
                        'year' => $timestamp->year,
                        'month' => 0,
                        'week' => 0,
                        'bucket_date' => $timestamp->copy()->startOfYear()->toDateString(),
                    ];

            default:
                throw new \InvalidArgumentException("Invalid timescale: $timescale");
        }
    }

    /**
     * Upsert time-series rows with additive updates
     */
    private function upsertTimeSeriesRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        DB::table('metrics')->upsert(
            $rows,
            ['timescale', 'location_type', 'location_id', 'user_id', 'year', 'month', 'week', 'bucket_date'],
            [
                'uploads' => DB::raw('GREATEST(uploads + VALUES(uploads), 0)'),
                'tags' => DB::raw('GREATEST(tags + VALUES(tags), 0)'),
                'brands' => DB::raw('GREATEST(brands + VALUES(brands), 0)'),
                'materials' => DB::raw('GREATEST(materials + VALUES(materials), 0)'),
                'custom_tags' => DB::raw('GREATEST(custom_tags + VALUES(custom_tags), 0)'),
                'litter' => DB::raw('GREATEST(litter + VALUES(litter), 0)'),
                'xp' => DB::raw('GREATEST(xp + VALUES(xp), 0)'),
                'updated_at' => DB::raw('VALUES(updated_at)'),
            ]
        );
    }

    /**
     * Get location hierarchy for a photo
     */
    private function getLocationHierarchy(Photo $photo): array
    {
        $locations = [[LocationType::Global, 0]];

        if ($photo->country_id) {
            $locations[] = [LocationType::Country, $photo->country_id];
        }
        if ($photo->state_id) {
            $locations[] = [LocationType::State, $photo->state_id];
        }
        if ($photo->city_id) {
            $locations[] = [LocationType::City, $photo->city_id];
        }

        return $locations;
    }

    /**
     * Compute fingerprint from normalized tags
     */
    private function computeFingerprint(array $tags): string
    {
        // Sort for consistency
        foreach ($tags as &$dimension) {
            ksort($dimension);
        }
        ksort($tags);

        $json = json_encode($tags, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return substr(hash('xxh128', $json), 0, 16);
    }

    /**
     * Calculate deltas between old and new tags
     */
    private function calculateTagDeltas(array $oldTags, array $newTags): array
    {
        $deltas = [];

        foreach (['categories', 'objects', 'materials', 'brands', 'custom_tags', 'types'] as $dimension) {
            $old = $oldTags[$dimension] ?? [];
            $new = $newTags[$dimension] ?? [];
            $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

            foreach ($allKeys as $key) {
                $delta = ($new[$key] ?? 0) - ($old[$key] ?? 0);
                if ($delta !== 0) {
                    $deltas[$dimension][$key] = $delta;
                }
            }
        }

        return $deltas;
    }

    /**
     * Check if delta is empty
     */
    private function isDeltaEmpty(array $deltas): bool
    {
        foreach ($deltas as $items) {
            if (!empty($items)) {
                return false;
            }
        }
        return true;
    }
}
