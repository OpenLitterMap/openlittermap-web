<?php

namespace App\Models\Location;

use App\Enums\LocationType;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class Location extends Model
{
    use HasFactory;

    /**
     * Get the Redis scope prefix for this location type.
     * Uses RedisKeys as the single source of truth for key naming.
     */
    protected function getRedisScope(): string
    {
        if ($this instanceof Country) return RedisKeys::country($this->id);
        if ($this instanceof State) return RedisKeys::state($this->id);
        if ($this instanceof City) return RedisKeys::city($this->id);

        return RedisKeys::global();
    }

    /**
     * Get the LocationType enum for this model
     */
    protected function getLocationType(): LocationType
    {
        if ($this instanceof Country) return LocationType::Country;
        if ($this instanceof State) return LocationType::State;
        if ($this instanceof City) return LocationType::City;

        return LocationType::Global;
    }

    // ─── Real-time stats (read directly from RedisMetricsCollector writes) ───

    /**
     * Total litter items tagged at this location
     */
    public function getTotalLitterRedisAttribute(): int
    {
        return (int) $this->redisGet(
            RedisKeys::stats($this->getRedisScope()),
            'litter'
        );
    }

    /**
     * Total photos uploaded at this location
     */
    public function getTotalPhotosRedisAttribute(): int
    {
        return (int) $this->redisGet(
            RedisKeys::stats($this->getRedisScope()),
            'photos'
        );
    }

    /**
     * Total XP earned at this location
     */
    public function getTotalXpAttribute(): int
    {
        return (int) $this->redisGet(
            RedisKeys::stats($this->getRedisScope()),
            'xp'
        );
    }

    /**
     * Approximate number of unique contributors.
     * Uses HyperLogLog (probabilistic, ~0.81% error, but O(1) and non-decrementable).
     */
    public function getTotalContributorsRedisAttribute(): int
    {
        return $this->safeRedis(
            fn () => Redis::pfCount(RedisKeys::hll($this->getRedisScope())),
            0
        );
    }

    // ─── Tag breakdowns (read from RedisKeys dimension hashes) ───

    /**
     * Category => count breakdown
     */
    public function getLitterDataAttribute(): array
    {
        return $this->getTagBreakdown(
            RedisKeys::categories($this->getRedisScope()),
            'category'
        );
    }

    /**
     * Object => count breakdown (top 20)
     */
    public function getObjectsDataAttribute(): array
    {
        return array_slice(
            $this->getTagBreakdown(
                RedisKeys::objects($this->getRedisScope()),
                'object'
            ),
            0, 20, true
        );
    }

    /**
     * Material => count breakdown
     */
    public function getMaterialsDataAttribute(): array
    {
        return $this->getTagBreakdown(
            RedisKeys::materials($this->getRedisScope()),
            'material'
        );
    }

    /**
     * Brand => count breakdown
     */
    public function getBrandsDataAttribute(): array
    {
        return $this->getTagBreakdown(
            RedisKeys::brands($this->getRedisScope()),
            'brand'
        );
    }

    // ─── Time-series (Option C: metrics table → cached in Redis with TTL) ───

    /**
     * Photos per month for the last 24 months
     */
    public function getPpmAttribute(): array
    {
        return $this->cachedTimeSeries('ppm', function () {
            $rows = DB::table('metrics')
                ->where('timescale', 3) // monthly
                ->where('location_type', $this->getLocationType()->value)
                ->where('location_id', $this->id)
                ->where('user_id', 0)
                ->where('bucket_date', '>=', now()->subMonths(24)->startOfMonth()->toDateString())
                ->orderBy('bucket_date')
                ->pluck('uploads', 'bucket_date');

            $ppm = [];
            foreach ($rows as $date => $count) {
                $key = substr($date, 0, 7); // 'YYYY-MM'
                $ppm[$key] = (int) $count;
            }

            return $ppm;
        });
    }

    /**
     * Alias for backwards compatibility
     */
    public function getTotalPpmAttribute(): array
    {
        return $this->getPpmAttribute();
    }

    /**
     * Daily photo activity for the last 7 days
     */
    public function getRecentActivityAttribute(): array
    {
        return $this->cachedTimeSeries('recent', function () {
            $rows = DB::table('metrics')
                ->where('timescale', 1) // daily
                ->where('location_type', $this->getLocationType()->value)
                ->where('location_id', $this->id)
                ->where('user_id', 0)
                ->where('bucket_date', '>=', now()->subDays(7)->toDateString())
                ->pluck('uploads', 'bucket_date');

            $activity = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $activity[$date] = (int) ($rows[$date] ?? 0);
            }

            return $activity;
        }, 300); // 5 min TTL — recent activity benefits from freshness
    }

    // ─── Rankings ───

    /**
     * Get top tags by dimension (uses ranking ZSETs)
     */
    public function getTopTags(string $dimension = 'objects', int $limit = 10): array
    {
        $scope = $this->getRedisScope();
        $rankKey = RedisKeys::ranking($scope, $dimension);

        $topItems = $this->safeRedis(
            fn () => Redis::zRevRange($rankKey, 0, $limit - 1, 'WITHSCORES'),
            []
        );

        if (empty($topItems)) {
            return [];
        }

        $dimensionMap = [
            'objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brands' => 'brand',
        ];

        $dim = $dimensionMap[$dimension] ?? null;
        $result = [];

        foreach ($topItems as $id => $count) {
            $name = $dim ? TagKeyCache::keyFor($dim, (int) $id) : null;
            if ($name) {
                $result[] = [
                    'id' => (int) $id,
                    'name' => $name,
                    'count' => (int) $count,
                ];
            }
        }

        return $result;
    }

    // ─── Utility ───

    public function getUpdatedAtDiffForHumansAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Get daily time series for a date range (queries metrics table directly)
     */
    public function getDailyTimeSeries(int $days = 30): array
    {
        $rows = DB::table('metrics')
            ->where('timescale', 1)
            ->where('location_type', $this->getLocationType()->value)
            ->where('location_id', $this->id)
            ->where('user_id', 0)
            ->where('bucket_date', '>=', now()->subDays($days)->toDateString())
            ->pluck('uploads', 'bucket_date');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $series[$date] = (int) ($rows[$date] ?? 0);
        }

        return $series;
    }

    /**
     * Check if location has recent activity
     */
    public function hasRecentActivity(int $days = 7): bool
    {
        return array_sum($this->getRecentActivityAttribute()) > 0;
    }

    /**
     * Get percentage of global totals
     */
    public function getGlobalPercentage(string $metric = 'litter'): float
    {
        $localValue = match ($metric) {
            'photos' => $this->total_photos_redis,
            'contributors' => $this->total_contributors_redis,
            default => $this->total_litter_redis,
        };

        if ($localValue === 0) {
            return 0.0;
        }

        $globalScope = RedisKeys::global();

        $globalTotal = match ($metric) {
            'photos' => (int) $this->redisGet(RedisKeys::stats($globalScope), 'photos'),
            'contributors' => $this->safeRedis(fn () => Redis::pfCount(RedisKeys::hll($globalScope)), 0),
            default => (int) $this->redisGet(RedisKeys::stats($globalScope), 'litter'),
        };

        return $globalTotal > 0 ? round(($localValue / $globalTotal) * 100, 2) : 0.0;
    }

    // ─── Relationships ───

    public function creator()
    {
        return $this->belongsTo('App\Models\Users\User', 'created_by');
    }

    public function photos()
    {
        return $this->hasMany('App\Models\Photo', $this->getForeignKey());
    }

    public function getForeignKey(): string
    {
        if ($this instanceof Country) return 'country_id';
        if ($this instanceof State) return 'state_id';
        if ($this instanceof City) return 'city_id';

        return 'location_id';
    }

    // ─── Private helpers ───

    /**
     * Safe HGET from Redis
     */
    private function redisGet(string $key, string $field): string|null
    {
        return $this->safeRedis(fn () => Redis::hGet($key, $field), null);
    }

    /**
     * Safe Redis operation wrapper
     */
    private function safeRedis(callable $operation, mixed $fallback = null): mixed
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            Log::error('Redis operation failed in Location model', [
                'location_type' => get_class($this),
                'location_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Get tag breakdown from a Redis hash, resolving IDs to names
     */
    private function getTagBreakdown(string $hashKey, string $tagType): array
    {
        $items = $this->safeRedis(fn () => Redis::hGetAll($hashKey), []);

        $totals = [];
        foreach ($items as $id => $count) {
            $name = TagKeyCache::keyFor($tagType, (int) $id);
            if ($name) {
                $totals[$name] = (int) $count;
            }
        }

        arsort($totals);

        return $totals;
    }

    /**
     * Option C: Query metrics table, cache result in Redis with TTL.
     *
     * Metrics table is source of truth. Redis is a read cache only.
     * Default TTL is 15 minutes — dashboard data doesn't need to be real-time.
     */
    private function cachedTimeSeries(string $suffix, callable $query, int $ttlSeconds = 900): array
    {
        $cacheKey = $this->getRedisScope() . ":cache:$suffix";

        // Try cache first
        $cached = $this->safeRedis(fn () => Redis::get($cacheKey), null);

        if ($cached !== null) {
            return json_decode($cached, true) ?: [];
        }

        // Cache miss — query metrics table
        $data = $query();

        // Store in Redis with TTL
        $this->safeRedis(fn () => Redis::setex($cacheKey, $ttlSeconds, json_encode($data)));

        return $data;
    }
}
