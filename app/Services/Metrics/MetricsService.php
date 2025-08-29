<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Enums\Timescale;
use App\Models\Photo;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\{DB, Log, Redis};

/**
 * Metrics system for OpenLitterMap v5
 *
 * Invariants:
 * - MySQL photo_metrics is the source of truth for all time-series
 * - Redis contains derived aggregates & rankings (rebuildable)
 * - Operations are idempotent via fingerprinting
 * - Deltas: create +1 upload, update 0 uploads, delete -1 upload
 * - Tags count = objects + materials + brands (not categories to avoid double-counting)
 * - All timestamps in UTC, ISO weeks use ISO year
 */
final class MetricsService
{
    // Location type mapping for database storage
    private const LOCATION_TYPES = [
        'global' => 0,
        'country' => 1,
        'state' => 2,
        'city' => 3,
    ];

    // Maximum items per ranking
    private const RANKING_LIMITS = [
        'objects' => 5000,
        'categories' => 200,
        'materials' => 500,
        'brands' => 2000,
        'contributors' => 10000,
    ];

    /**
     * Process a photo - handles create, update, or skip
     */
    public function processPhoto(Photo $photo): void
    {
        $metrics = $this->extractMetricsFromPhoto($photo);
        $fingerprint = $this->computeFingerprint($metrics['tags']);

        // Skip if unchanged
        if ($photo->processed_fp === $fingerprint) {
            return;
        }

        // Route to appropriate handler
        if ($photo->processed_at !== null) {
            $this->updateExistingPhoto($photo, $metrics, $fingerprint);
        } else {
            $this->createNewPhoto($photo, $metrics, $fingerprint);
        }
    }

    /**
     * Delete a photo's metrics
     */
    public function deletePhoto(Photo $photo): void
    {
        if ($photo->processed_at === null) {
            return;
        }

        $oldTags = json_decode($photo->processed_tags ?? '{}', true);

        // Calculate negative metrics from stored tags
        $negativeMetrics = [
            'tags_count' => -(
                array_sum($oldTags['objects'] ?? []) +
                array_sum($oldTags['materials'] ?? []) +
                array_sum($oldTags['brands'] ?? [])
            ),
            'brands_count' => -array_sum($oldTags['brands'] ?? []),
            'litter' => -array_sum($oldTags['objects'] ?? []),
        ];

        DB::transaction(function() use ($photo, $negativeMetrics, $oldTags) {
            // Apply negative deltas with -1 upload
            $rows = $this->buildTimeSeriesRows($photo, $negativeMetrics, -1);
            $this->upsertTimeSeriesRows($rows);

            // Clear processing data
            $photo->update([
                'processed_at' => null,
                'processed_fp' => null,
                'processed_tags' => null,
            ]);
        });

        // Update Redis with negative values
        $this->updateRedisMetrics($photo, [
            'tags' => $oldTags,
            'litter' => -array_sum($oldTags['objects'] ?? []),
        ], 'delete');
    }

    /**
     * Process a new photo
     */
    private function createNewPhoto(Photo $photo, array $metrics, string $fingerprint): void
    {
        DB::transaction(function() use ($photo, $metrics, $fingerprint) {
            // Use upsert for idempotency (handles retries)
            $rows = $this->buildTimeSeriesRows($photo, $metrics, 1);
            $this->upsertTimeSeriesRows($rows);

            // Mark as processed
            $photo->update([
                'processed_at' => now('UTC'),
                'processed_fp' => $fingerprint,
                'processed_tags' => json_encode($metrics['tags'], JSON_NUMERIC_CHECK),
            ]);
        });

        // Update Redis after commit
        $this->updateRedisMetrics($photo, $metrics, 'create');
    }

    /**
     * Update an existing photo
     */
    private function updateExistingPhoto(Photo $photo, array $newMetrics, string $newFingerprint): void
    {
        $oldTags = json_decode($photo->processed_tags ?? '{}', true);
        $tagDeltas = $this->calculateTagDeltas($oldTags, $newMetrics['tags']);

        // No actual changes
        if ($this->isDeltaEmpty($tagDeltas)) {
            $photo->update(['processed_fp' => $newFingerprint]);
            return;
        }

        // Calculate metric deltas (not double-counting categories)
        $deltaMetrics = [
            'tags_count' => (
                array_sum($tagDeltas['objects'] ?? []) +
                array_sum($tagDeltas['materials'] ?? []) +
                array_sum($tagDeltas['brands'] ?? [])
            ),
            'brands_count' => array_sum($tagDeltas['brands'] ?? []),
            'litter' => array_sum($tagDeltas['objects'] ?? []),
        ];

        DB::transaction(function() use ($photo, $newMetrics, $newFingerprint, $deltaMetrics) {
            // Apply deltas (0 uploads for updates)
            $rows = $this->buildTimeSeriesRows($photo, $deltaMetrics, 0);
            $this->upsertTimeSeriesRows($rows);

            // Update photo record
            $photo->update([
                'processed_fp' => $newFingerprint,
                'processed_tags' => json_encode($newMetrics['tags'], JSON_NUMERIC_CHECK),
            ]);
        });

        // Update Redis with deltas
        $this->updateRedisMetrics($photo, [
            'tags' => $tagDeltas,
            'litter' => $deltaMetrics['litter'],
        ], 'update');
    }

    /**
     * Extract metrics from photo summary
     */
    private function extractMetricsFromPhoto(Photo $photo): array
    {
        $summary = $photo->summary ?? [];
        $tags = ['categories' => [], 'objects' => [], 'materials' => [], 'brands' => []];
        $totalLitter = 0;
        $totalBrands = 0;

        foreach ($summary['tags'] ?? [] as $categoryKey => $objects) {
            if (!is_array($objects)) continue;

            $categoryTotal = 0;
            foreach ($objects as $objectKey => $data) {
                if (!is_array($data)) continue;

                $quantity = (int)($data['quantity'] ?? 0);
                if ($quantity <= 0) continue;

                // Objects
                $objectId = is_numeric($objectKey) ? (int)$objectKey : $objectKey;
                $tags['objects'][$objectId] = ($tags['objects'][$objectId] ?? 0) + $quantity;
                $categoryTotal += $quantity;
                $totalLitter += $quantity;

                // Materials
                foreach ($data['materials'] ?? [] as $key => $count) {
                    $id = is_numeric($key) ? (int)$key : $key;
                    $tags['materials'][$id] = ($tags['materials'][$id] ?? 0) + (int)$count;
                }

                // Brands
                foreach ($data['brands'] ?? [] as $key => $count) {
                    $id = is_numeric($key) ? (int)$key : $key;
                    $brandCount = (int)$count;
                    $tags['brands'][$id] = ($tags['brands'][$id] ?? 0) + $brandCount;
                    $totalBrands += $brandCount;
                }
            }

            // Category totals
            if ($categoryTotal > 0) {
                $categoryId = is_numeric($categoryKey) ? (int)$categoryKey : $categoryKey;
                $tags['categories'][$categoryId] = $categoryTotal;
            }
        }

        // Tags count excludes categories to avoid double-counting
        $tagsCount = array_sum($tags['objects']) +
            array_sum($tags['materials']) +
            array_sum($tags['brands']);

        return [
            'tags' => $tags,
            'tags_count' => $tagsCount,
            'brands_count' => $totalBrands,
            'litter' => $totalLitter,
            'xp' => (int)($photo->xp ?? 0),
        ];
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

        foreach (['categories', 'objects', 'materials', 'brands'] as $dimension) {
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

    /**
     * Build time-series rows for all timescales and locations
     */
    private function buildTimeSeriesRows(Photo $photo, array $metrics, int $uploadsDelta): array
    {
        $timestamp = $photo->created_at->copy()->utc();
        $locations = $this->getLocationHierarchy($photo);
        $rows = [];

        foreach ($locations as [$type, $id]) {
            // All-time (timescale 0)
            $rows[] = $this->buildSingleRow(0, $type, $id, $timestamp, $metrics, $uploadsDelta);

            // Daily (timescale 1)
            $rows[] = $this->buildSingleRow(1, $type, $id, $timestamp, $metrics, $uploadsDelta);

            // Weekly (timescale 2)
            $rows[] = $this->buildSingleRow(2, $type, $id, $timestamp, $metrics, $uploadsDelta);

            // Monthly (timescale 3)
            $rows[] = $this->buildSingleRow(3, $type, $id, $timestamp, $metrics, $uploadsDelta);

            // Yearly (timescale 4)
            $rows[] = $this->buildSingleRow(4, $type, $id, $timestamp, $metrics, $uploadsDelta);
        }

        return $rows;
    }

    /**
     * Build a single time-series row
     */
    private function buildSingleRow(
        int $timescale,
        string $locationType,
        int $locationId,
        $timestamp,
        array $metrics,
        int $uploadsDelta
    ): array {
        $base = [
            'timescale' => $timescale,
            'location_type' => self::LOCATION_TYPES[$locationType],
            'location_id' => $locationId,
            'uploads' => $uploadsDelta,
            'tags' => $metrics['tags_count'] ?? 0,
            'brands' => $metrics['brands_count'] ?? 0,
            'litter' => $metrics['litter'] ?? 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ];

        switch ($timescale) {
            case 0: // All-time
                return $base + [
                        'year' => 0,
                        'month' => 0,
                        'iso_week' => 0,
                        'day' => '1970-01-01',
                    ];

            case 1: // Daily
                return $base + [
                        'year' => $timestamp->year,
                        'month' => $timestamp->month,
                        'iso_week' => (int)$timestamp->format('W'),
                        'day' => $timestamp->toDateString(),
                    ];

            case 2: // Weekly (ISO)
                $weekStart = $timestamp->copy()->startOfWeek();
                return $base + [
                        'year' => (int)$timestamp->format('o'), // ISO year
                        'month' => $weekStart->month,
                        'iso_week' => (int)$timestamp->format('W'),
                        'day' => $weekStart->toDateString(),
                    ];

            case 3: // Monthly
                return $base + [
                        'year' => $timestamp->year,
                        'month' => $timestamp->month,
                        'iso_week' => 0,
                        'day' => $timestamp->copy()->startOfMonth()->toDateString(),
                    ];

            case 4: // Yearly
                return $base + [
                        'year' => $timestamp->year,
                        'month' => 0,
                        'iso_week' => 0,
                        'day' => $timestamp->copy()->startOfYear()->toDateString(),
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
        DB::table('photo_metrics')->upsert(
            $rows,
            ['timescale', 'location_type', 'location_id', 'year', 'month', 'iso_week', 'day'],
            [
                'uploads' => DB::raw('uploads + VALUES(uploads)'),
                'tags' => DB::raw('tags + VALUES(tags)'),
                'brands' => DB::raw('brands + VALUES(brands)'),
                'litter' => DB::raw('litter + VALUES(litter)'),
                'updated_at' => DB::raw('VALUES(updated_at)'),
            ]
        );
    }

    /**
     * Get location hierarchy for a photo
     */
    private function getLocationHierarchy(Photo $photo): array
    {
        $locations = [['global', 0]];

        if ($photo->country_id) {
            $locations[] = ['country', $photo->country_id];
        }
        if ($photo->state_id) {
            $locations[] = ['state', $photo->state_id];
        }
        if ($photo->city_id) {
            $locations[] = ['city', $photo->city_id];
        }

        return $locations;
    }

    /**
     * Update Redis metrics
     */
    private function updateRedisMetrics(Photo $photo, array $metrics, string $operation): void
    {
        try {
            $scopes = RedisKeys::getPhotoScopes($photo);
            $userId = $photo->user_id;

            Redis::pipeline(function($pipe) use ($scopes, $userId, $metrics, $operation, $photo) {
                foreach ($scopes as $scope) {
                    // Update stats based on operation
                    if ($operation === 'create') {
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'photos', 1);
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', $metrics['litter']);

                        // HyperLogLog for unique contributors (append-only)
                        $pipe->pfAdd(RedisKeys::hll($scope), (string)$userId);

                        // Contributor ranking (can be decremented)
                        $pipe->zIncrBy(RedisKeys::contributorRanking($scope), 1, (string)$userId);

                    } elseif ($operation === 'update') {
                        // Update stats with litter delta
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', $metrics['litter']);

                    } elseif ($operation === 'delete') {
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'photos', -1);
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', $metrics['litter']); // Already negative

                        // Decrement contributor ranking (HLL cannot be decremented)
                        $pipe->zIncrBy(RedisKeys::contributorRanking($scope), -1, (string)$userId);
                    }

                    // Update tags and rankings
                    foreach (['categories', 'objects', 'materials', 'brands'] as $dimension) {
                        $items = $metrics['tags'][$dimension] ?? [];
                        if (empty($items)) continue;

                        $hashKey = $this->getRedisHashKey($scope, $dimension);
                        $rankKey = RedisKeys::ranking($scope, $dimension);

                        foreach ($items as $id => $delta) {
                            // For delete, values are already positive in $metrics['tags']
                            $value = $operation === 'delete' ? -$delta : $delta;

                            $pipe->hIncrBy($hashKey, (string)$id, $value);
                            $pipe->zIncrBy($rankKey, $value, (string)$id);
                        }

                        // Track ranking for trimming
                        $pipe->sAdd('ranking:keys', $rankKey);
                    }
                }

                // User-specific updates
                if ($operation === 'create') {
                    $userScope = RedisKeys::user($userId);
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'uploads', 1);
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', $metrics['xp']);
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'litter', $metrics['litter']);

                    // Update streak bitmap
                    $dayIndex = $this->calculateDayIndex($photo->created_at);
                    $pipe->setBit(RedisKeys::userBitmap($userId), $dayIndex, 1);

                } elseif ($operation === 'delete') {
                    $userScope = RedisKeys::user($userId);
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'uploads', -1);
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', -($metrics['xp'] ?? 0));
                    $pipe->hIncrBy(RedisKeys::stats($userScope), 'litter', $metrics['litter']); // Already negative
                }
            });

        } catch (\Exception $e) {
            Log::error('Redis update failed', [
                'photo_id' => $photo->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Redis hash key for dimension
     */
    private function getRedisHashKey(string $scope, string $dimension): string
    {
        return match($dimension) {
            'categories' => RedisKeys::categories($scope),
            'objects' => RedisKeys::objects($scope),
            'materials' => RedisKeys::materials($scope),
            'brands' => RedisKeys::brands($scope),
            default => throw new \InvalidArgumentException("Unknown dimension: $dimension"),
        };
    }

    /**
     * Calculate day index for bitmap (consistent epoch)
     */
    private function calculateDayIndex($timestamp): int
    {
        $epoch = new \DateTime('2020-01-01', new \DateTimeZone('UTC'));
        $current = new \DateTime($timestamp->format('Y-m-d'), new \DateTimeZone('UTC'));
        return $epoch->diff($current)->days;
    }

    /**
     * Trim rankings to configured limits
     */
    public function trimRankings(): array
    {
        $stats = ['trimmed' => 0, 'keys' => 0];
        $keys = Redis::sMembers('ranking:keys');

        foreach ($keys as $key) {
            $limit = $this->getRankingLimit($key);
            if ($limit === 0) continue;

            $size = Redis::zCard($key);
            if ($size <= $limit) continue;

            // Calculate how many to remove
            $toRemove = $size - $limit;
            $removed = Redis::zRemRangeByRank($key, 0, $toRemove - 1);

            if ($removed > 0) {
                $stats['trimmed'] += $removed;
                $stats['keys']++;

                Log::info('Trimmed ranking', [
                    'key' => $key,
                    'removed' => $removed,
                    'new_size' => $size - $removed,
                ]);
            }
        }

        return $stats;
    }

    /**
     * Get ranking limit for a key
     */
    private function getRankingLimit(string $key): int
    {
        foreach (self::RANKING_LIMITS as $type => $limit) {
            if (str_contains($key, ":$type")) {
                return $limit;
            }
        }
        return 1000; // Default limit
    }
}
