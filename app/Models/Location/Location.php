<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

abstract class Location extends Model
{
    use HasFactory;

    /**
     * Get the Redis prefix for this location type (FIXED to match service keys)
     */
    protected function getLocationRedisPrefix(): string
    {
        if ($this instanceof Country) return "{c:{$this->id}}";
        if ($this instanceof State) return "{s:{$this->id}}";
        if ($this instanceof City) return "{ci:{$this->id}}";
        return "{c:{$this->id}}";
    }

    /**
     * Safe Redis operation wrapper
     */
    private function safeRedisOperation(callable $operation, $fallback = null)
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            Log::error('Redis operation failed in Location model', [
                'location_type' => get_class($this),
                'location_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            return $fallback ?? 0;
        }
    }

    /**
     * Return the total_litter value from Redis (sum of all objects)
     */
    public function getTotalLitterRedisAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();

        // Try denormalized total first
        $stats = $this->safeRedisOperation(
            fn() => Redis::hget("$prefix:stats", 'litter'),
            null
        );

        if ($stats !== null) {
            return (int) $stats;
        }

        // Fallback to calculating from hash
        $objects = $this->safeRedisOperation(
            fn() => Redis::hgetall("$prefix:t"),
            []
        );

        $total = 0;
        foreach ($objects as $count) {
            $total += (int)$count;
        }

        return $total;
    }

    /**
     * Return the total_photos value from Redis
     */
    public function getTotalPhotosRedisAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();
        $stats = $this->safeRedisOperation(
            fn() => Redis::hget("$prefix:stats", 'photos'),
            0
        );

        return (int)$stats;
    }

    /**
     * Return the total number of people who uploaded a photo from Redis
     */
    public function getTotalContributorsRedisAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();
        return $this->safeRedisOperation(
            fn() => Redis::scard("$prefix:users"),
            0
        );
    }

    /**
     * Return array of category => count with proper names
     */
    public function getLitterDataAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $categories = $this->safeRedisOperation(
            fn() => Redis::hgetall("$prefix:c"),
            []
        );

        $totals = [];
        foreach ($categories as $categoryId => $count) {
            $categoryName = TagKeyCache::keyFor('category', (int)$categoryId);
            if ($categoryName) {
                $totals[$categoryName] = (int)$count;
            }
        }

        return $totals;
    }

    /**
     * Return array of brand => count with proper names
     */
    public function getBrandsDataAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $brands = $this->safeRedisOperation(
            fn() => Redis::hgetall("$prefix:brands"),
            []
        );

        $totals = [];
        foreach ($brands as $brandId => $count) {
            $brandName = TagKeyCache::keyFor('brand', (int)$brandId);
            if ($brandName) {
                $totals[$brandName] = (int)$count;
            }
        }

        return $totals;
    }

    /**
     * Get the Photos Per Month attribute - using new structure
     */
    public function getPpmAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $ppm = [];

        // Check last 24 months deterministically (no KEYS command)
        for ($i = 0; $i < 24; $i++) {
            $month = now()->subMonths($i)->format('Y-m');
            $data = $this->safeRedisOperation(
                fn() => Redis::hgetall("$prefix:$month:t"),
                []
            );
            if (!empty($data) && isset($data['p'])) {
                $ppm[$month] = (int)$data['p'];
            }
        }

        ksort($ppm);
        return $ppm;
    }

    /**
     * Get total photos per month (same as ppm for compatibility)
     */
    public function getTotalPpmAttribute(): array
    {
        return $this->getPpmAttribute();
    }

    /**
     * Get updatedAtDiffForHumans
     */
    public function getUpdatedAtDiffForHumansAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Get object breakdown (litter objects with names)
     */
    public function getObjectsDataAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $objects = $this->safeRedisOperation(
            fn() => Redis::hgetall("$prefix:t"),
            []
        );

        $totals = [];
        foreach ($objects as $objectId => $count) {
            $objectName = TagKeyCache::keyFor('object', (int)$objectId);
            if ($objectName) {
                $totals[$objectName] = (int)$count;
            }
        }

        arsort($totals);
        return array_slice($totals, 0, 20, true); // Top 20
    }

    /**
     * Get material breakdown
     */
    public function getMaterialsDataAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $materials = $this->safeRedisOperation(
            fn() => Redis::hgetall("$prefix:m"),
            []
        );

        $totals = [];
        foreach ($materials as $materialId => $count) {
            $materialName = TagKeyCache::keyFor('material', (int)$materialId);
            if ($materialName) {
                $totals[$materialName] = (int)$count;
            }
        }

        arsort($totals);
        return $totals;
    }

    /**
     * Get recent activity (last 7 days)
     */
    public function getRecentActivityAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $tsKey = "$prefix:t:p";

        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $this->safeRedisOperation(
                fn() => Redis::hget($tsKey, $date),
                0
            );
            $activity[$date] = (int)$count;
        }

        return $activity;
    }

    /**
     * Get total XP for this location
     */
    public function getTotalXpAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();
        $xp = 0;

        // Sum XP from monthly aggregates
        for ($i = 0; $i < 24; $i++) {
            $month = now()->subMonths($i)->format('Y-m');
            $data = $this->safeRedisOperation(
                fn() => Redis::hgetall("$prefix:$month:t"),
                []
            );
            if (!empty($data) && isset($data['xp'])) {
                $xp += (int)$data['xp'];
            }
        }

        return $xp;
    }

    /**
     * Get daily time series for a date range
     */
    public function getDailyTimeSeries(int $days = 30): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $tsKey = "$prefix:t:p";

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $this->safeRedisOperation(
                fn() => Redis::hget($tsKey, $date),
                0
            );
            $series[$date] = (int)$count;
        }

        return $series;
    }

    /**
     * Get top contributors for this location
     */
    public function getTopContributors(int $limit = 10): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $userIds = $this->safeRedisOperation(
            fn() => Redis::smembers("$prefix:users"),
            []
        );

        if (empty($userIds)) {
            return [];
        }

        // This is a simplified version - in production you might want to
        // track contributor scores in a ZSET for better performance
        $contributors = [];
        foreach ($userIds as $userId) {
            $userPhotos = $this->photos()
                ->where('user_id', $userId)
                ->whereNotNull('processed_at')
                ->count();

            if ($userPhotos > 0) {
                $contributors[$userId] = $userPhotos;
            }
        }

        arsort($contributors);
        return array_slice($contributors, 0, $limit, true);
    }

    /**
     * Check if location has recent activity
     */
    public function hasRecentActivity(int $days = 7): bool
    {
        $activity = $this->getRecentActivityAttribute();
        return array_sum($activity) > 0;
    }

    /**
     * Get percentage of global totals (FIXED)
     */
    public function getGlobalPercentage(string $metric = 'litter'): float
    {
        $localValue = match($metric) {
            'photos' => $this->total_photos_redis,
            'contributors' => $this->total_contributors_redis,
            default => $this->total_litter_redis,
        };

        if ($localValue === 0) {
            return 0.0;
        }

        // Get global total (FIXED to use correct keys and methods)
        $globalTotal = $this->safeRedisOperation(function() use ($metric) {
            return match($metric) {
                'photos' => (int) Redis::hGet('{g}:stats', 'photos'),
                'contributors' => Redis::sCard('{g}:users'),
                default => (int) Redis::hGet('{g}:stats', 'litter'),
            };
        }, 0);

        // Fallback for litter if stats not populated
        if ($globalTotal === 0 && $metric === 'litter') {
            $globalTotal = $this->calculateTotalFromHash('{g}:t');
        }

        return $globalTotal > 0 ? round(($localValue / $globalTotal) * 100, 2) : 0.0;
    }

    /**
     * Calculate total from hash (migration helper)
     */
    private function calculateTotalFromHash(string $hashKey): int
    {
        $items = $this->safeRedisOperation(
            fn() => Redis::hgetall($hashKey),
            []
        );

        $total = 0;
        foreach ($items as $count) {
            $total += (int) $count;
        }

        return $total;
    }

    /**
     * Get top tags using ranking ZSETs for performance (FIXED key pattern)
     */
    public function getTopTags(string $dimension = 'objects', int $limit = 10): array
    {
        $prefix = $this->getLocationRedisPrefix();

        // Try to get from ranking ZSET first (FIXED: consistent key pattern)
        $rankKey = "$prefix:rank:$dimension";
        $topItems = $this->safeRedisOperation(
            fn() => Redis::zRevRange($rankKey, 0, $limit - 1, 'WITHSCORES'),
            []
        );

        if (empty($topItems)) {
            // Fallback to hash if ZSETs not populated
            $hashKey = match($dimension) {
                'objects' => "$prefix:t",
                'categories' => "$prefix:c",
                'materials' => "$prefix:m",
                'brands' => "$prefix:brands",
                default => "$prefix:t"
            };

            $allItems = $this->safeRedisOperation(
                fn() => Redis::hgetall($hashKey),
                []
            );

            if (empty($allItems)) {
                return [];
            }

            arsort($allItems);
            $topItems = array_slice($allItems, 0, $limit, true);
        }

        $result = [];
        foreach ($topItems as $id => $count) {
            $name = $this->getTagName($dimension, (int)$id);
            if ($name) {
                $result[] = [
                    'id' => (int)$id,
                    'name' => $name,
                    'count' => (int)$count
                ];
            }
        }

        return $result;
    }

    /**
     * Helper to get tag name from cache
     */
    private function getTagName(string $dimension, int $id): ?string
    {
        $dimensionMap = [
            'objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brands' => 'brand',
        ];

        $dim = $dimensionMap[$dimension] ?? null;
        return $dim ? TagKeyCache::keyFor($dim, $id) : null;
    }

    /**
     * Common relationships that all locations have
     */
    public function creator()
    {
        return $this->belongsTo('App\Models\Users\User', 'created_by');
    }

    public function lastUploader()
    {
        return $this->belongsTo('App\Models\Users\User', 'user_id_last_uploaded');
    }

    public function photos()
    {
        return $this->hasMany('App\Models\Photo', $this->getForeignKey());
    }

    /**
     * Get the foreign key name for this location type
     */
    public function getForeignKey(): string
    {
        if ($this instanceof Country) return 'country_id';
        if ($this instanceof State) return 'state_id';
        if ($this instanceof City) return 'city_id';
        return 'location_id';
    }

    /**
     * Scope for verified locations
     */
    public function scopeVerified($query)
    {
        return $query->where('manual_verify', true);
    }

    /**
     * Scope for locations with recent activity
     */
    public function scopeActive($query, int $days = 30)
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }
}
