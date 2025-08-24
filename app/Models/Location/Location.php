<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Facades\Redis;

abstract class Location extends Model
{
    use HasFactory;

    /**
     * Get the Redis prefix for this location type
     */
    protected function getLocationRedisPrefix(): string
    {
        if ($this instanceof Country) return 'c';
        if ($this instanceof State) return 's';
        if ($this instanceof City) return 'ci';
        return 'c';
    }

    /**
     * Return the total_litter value from Redis (sum of all objects)
     */
    public function getTotalLitterRedisAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();
        $objects = Redis::hgetall("{$prefix}:{$this->id}:t");

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
        $stats = Redis::hget("{$prefix}:{$this->id}:stats", 'photos');

        return (int)($stats ?? 0);
    }

    /**
     * Return the total number of people who uploaded a photo from Redis
     */
    public function getTotalContributorsRedisAttribute(): int
    {
        $prefix = $this->getLocationRedisPrefix();
        return (int)Redis::scard("{$prefix}:{$this->id}:users");
    }

    /**
     * Return array of category => count with proper names
     */
    public function getLitterDataAttribute(): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $categories = Redis::hgetall("{$prefix}:{$this->id}:c");

        $totals = [];
        foreach ($categories as $categoryId => $count) {
            // Use keyFor instead of getKey
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
        $brands = Redis::hgetall("{$prefix}:{$this->id}:brands");

        $totals = [];
        foreach ($brands as $brandId => $count) {
            // Use keyFor instead of getKey
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
            $data = Redis::hgetall("{$prefix}:{$this->id}:{$month}:t");
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
        $objects = Redis::hgetall("{$prefix}:{$this->id}:t");

        $totals = [];
        foreach ($objects as $objectId => $count) {
            // Use keyFor instead of getKey
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
        $materials = Redis::hgetall("{$prefix}:{$this->id}:m");

        $totals = [];
        foreach ($materials as $materialId => $count) {
            // Use keyFor instead of getKey
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
        $tsKey = "{$prefix}:{$this->id}:t:p";

        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = Redis::hget($tsKey, $date) ?: 0;
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
            $data = Redis::hgetall("{$prefix}:{$this->id}:{$month}:t");
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
        $tsKey = "{$prefix}:{$this->id}:t:p";

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = Redis::hget($tsKey, $date) ?: 0;
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
        $userIds = Redis::smembers("{$prefix}:{$this->id}:users");

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
     * Get percentage of global totals
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

        // Get global total
        $globalTotal = match($metric) {
            'photos' => array_sum(Redis::hgetall('{g}:stats')),
            'contributors' => Redis::scard('{g}:users'),
            default => array_sum(Redis::hgetall('{g}:t')),
        };

        return $globalTotal > 0 ? round(($localValue / $globalTotal) * 100, 2) : 0.0;
    }

    /**
     * Get top tags using ranking ZSETs for performance
     */
    public function getTopTags(string $dimension = 'objects', int $limit = 10): array
    {
        $prefix = $this->getLocationRedisPrefix();
        $scope = "{$prefix}:{$this->id}";

        // Try to get from ranking ZSET first
        $rankKey = "rank:$scope:$dimension";
        $topItems = Redis::zRevRange($rankKey, 0, $limit - 1, 'WITHSCORES');

        if (empty($topItems)) {
            // Fallback to hash if ZSETs not populated
            $hashKey = match($dimension) {
                'objects' => "$scope:t",
                'categories' => "$scope:c",
                'materials' => "$scope:m",
                'brands' => "$scope:brands",
                default => "$scope:t"
            };

            $allItems = Redis::hgetall($hashKey);
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
