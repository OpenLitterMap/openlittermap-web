<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class RedisDataController extends Controller
{
    /**
     * Get overview of all Redis data
     */
    public function index(Request $request): JsonResponse
    {
        $data = [
            'users' => $this->getUsersData($request->get('limit', 50)),
            'global' => $this->getGlobalData(),
            'timeSeries' => $this->getTimeSeriesData(),
            'geo' => $this->getGeoData(),
            'stats' => $this->getRedisStats(),
            'summary' => $this->getDataSummary(),
        ];

        return response()->json($data);
    }

    /**
     * Get specific user's Redis data
     */
    public function show(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $data = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'metrics' => RedisMetricsCollector::getUserCountsWithKeys($userId),
            'raw' => RedisMetricsCollector::getUserCounts($userId),
            'activity' => $this->getUserActivity($userId),
        ];

        return response()->json($data);
    }

    /**
     * Get users with Redis data
     */
    private function getUsersData(int $limit): array
    {
        // Get all user stat keys
        $keys = Redis::keys('{u:*}:stats');
        $users = [];

        foreach (array_slice($keys, 0, $limit) as $key)
        {
            if (preg_match('/{u:(\d+)}:stats/', $key, $matches))
            {
                $userId = (int) $matches[1];
                $stats = Redis::hGetAll($key);

                if (!empty($stats)) {
                    $user = User::find($userId);
                    if ($user) {
                        $users[] = [
                            'id' => $userId,
                            'name' => $user->name ?? 'Unknown',
                            'email' => $user->email ?? null,
                            'uploads' => (int) ($stats['uploads'] ?? 0),
                            'xp' => (float) ($stats['xp'] ?? 0),
                            'streak' => (int) ($stats['streak'] ?? 0),
                        ];
                    }
                }
            }
        }

        // Sort by uploads descending
        usort($users, fn($a, $b) => $b['uploads'] <=> $a['uploads']);

        return $users;
    }

    /**
     * Get global metrics
     */
    private function getGlobalData(): array
    {
        $keys = [
            'categories' => '{g}:c',
            'objects' => '{g}:t',
            'materials' => '{g}:m',
            'brands' => '{g}:brands',
        ];

        $data = [];
        foreach ($keys as $type => $key) {
            $values = Redis::hGetAll($key);

            // Convert string keys back to IDs and get proper names
            $namedValues = [];
            foreach ($values as $id => $count) {
                $namedValues[$id] = (int)$count;
            }

            $data[$type] = [
                'total' => array_sum($namedValues),
                'unique' => count($namedValues),
                'top10' => $this->getTop($namedValues, 10),
            ];
        }

        return $data;
    }

    /**
     * Get time series data
     */
    private function getTimeSeriesData(): array
    {
        $pattern = '{g}:*:t';
        $keys = Redis::keys($pattern);
        $series = [];

        foreach ($keys as $key) {
            if (preg_match('/{g}:(\d{4}-\d{2}):t/', $key, $matches)) {
                $month = $matches[1];
                $data = Redis::hGetAll($key);
                $series[$month] = [
                    'photos' => (int) ($data['p'] ?? 0),
                    'xp' => (float) ($data['xp'] ?? 0),
                ];
            }
        }

        // Sort by month descending (most recent first)
        krsort($series);

        // Limit to last 12 months
        $series = array_slice($series, 0, 12, true);

        // Resort ascending for display
        ksort($series);

        return $series;
    }

    /**
     * Get geographic data summary
     */
    private function getGeoData(): array
    {
        $data = [
            'countries' => $this->getGeoSummary('c:*:t:p'),
            'states' => $this->getGeoSummary('s:*:t:p'),
            'cities' => $this->getGeoSummary('ci:*:t:p'),
        ];

        return $data;
    }

    /**
     * Get summary for geographic pattern
     */
    private function getGeoSummary(string $pattern): array
    {
        $keys = Redis::keys($pattern);
        $totalPhotos = 0;
        $locations = 0;

        foreach ($keys as $key) {
            $values = Redis::hGetAll($key);
            $totalPhotos += array_sum($values);
            $locations++;
        }

        return [
            'locations' => $locations,
            'totalPhotos' => $totalPhotos,
        ];
    }

    /**
     * Get Redis server stats
     */
    private function getRedisStats(): array
    {
        $info = Redis::info();

        return [
            'usedMemory' => $info['used_memory_human'] ?? 'N/A',
            'totalKeys' => Redis::dbSize(),
            'connectedClients' => $info['connected_clients'] ?? 0,
            'uptime' => $info['uptime_in_days'] ?? 0,
        ];
    }

    private function getDataSummary(): array
    {
        // Count all user keys
        $userStatsKeys = count(Redis::keys('{u:*}:stats'));

        // Get total uploads across all users
        $totalUploads = 0;
        $totalXp = 0;
        $activeUsers = 0;

        $keys = Redis::keys('{u:*}:stats');

        foreach ($keys as $key) {
            $stats = Redis::hGetAll($key);
            if (!empty($stats)) {
                $uploads = (int)($stats['uploads'] ?? 0);
                $xp = (float)($stats['xp'] ?? 0);

                $totalUploads += $uploads;
                $totalXp += $xp;

                if ($uploads > 0) {
                    $activeUsers++;
                }
            }
        }

        // prob cache this or move to redis
        $totalPhotosInDb = Photo::count();

        return [
            'totalUsers' => $userStatsKeys,
            'activeUsers' => $activeUsers,
            'totalUploads' => $totalUploads,
            'totalPhotosInDb' => $totalPhotosInDb,
            'totalXp' => $totalXp,
            'avgUploadsPerUser' => $activeUsers > 0 ? round($totalUploads / $activeUsers, 1) : 0,
            'avgXpPerUpload' => $totalUploads > 0 ? round($totalXp / $totalUploads, 1) : 0,
        ];
    }

    /**
     * Get user activity patterns
     */
    private function getUserActivity(int $userId): array
    {
        $bitmapKey = sprintf('{u:%d}:up', $userId);

        // Get the last 30 days of activity
        $today = Carbon::now()->startOfDay();
        $activity = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $dayIndex = $date->diffInDays(Carbon::createFromTimestamp(0));

            // Check if user was active on this day
            $wasActive = Redis::getBit($bitmapKey, $dayIndex);

            $activity[] = [
                'date' => $date->format('Y-m-d'),
                'active' => (bool)$wasActive,
            ];
        }

        // Calculate activity stats
        $activeDays = array_filter($activity, fn($day) => $day['active']);
        $activityRate = count($activeDays) / 30 * 100;

        return [
            'last30Days' => $activity,
            'activeDays' => count($activeDays),
            'activityRate' => round($activityRate, 1),
        ];
    }

    /**
     * Get top N items from array
     */
    private function getTop(array $items, int $n): array
    {
        arsort($items);
        return array_slice($items, 0, $n, true);
    }

    /**
     * Clear all Redis data (careful!)
     */
    public function destroy(Request $request): JsonResponse
    {
        if (!$request->has('confirm') || $request->get('confirm') !== 'DELETE_ALL_REDIS_DATA') {
            return response()->json(['error' => 'Confirmation required'], 400);
        }

        Redis::flushdb();

        return response()->json(['message' => 'All Redis data cleared']);
    }

    /**
     * Get Redis performance metrics
     */
    public function performance(): JsonResponse
    {
        $info = Redis::info();
        $commandStats = Redis::info('commandstats');

        $performance = [
            'memory' => [
                'used' => $info['used_memory_human'] ?? 'N/A',
                'peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'rss' => $info['used_memory_rss_human'] ?? 'N/A',
                'fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 0,
            ],
            'performance' => [
                'ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'evicted_keys' => $info['evicted_keys'] ?? 0,
                'expired_keys' => $info['expired_keys'] ?? 0,
            ],
            'connections' => [
                'connected' => $info['connected_clients'] ?? 0,
                'blocked' => $info['blocked_clients'] ?? 0,
                'total_received' => $info['total_connections_received'] ?? 0,
            ],
            'persistence' => [
                'last_save_time' => isset($info['rdb_last_save_time'])
                    ? date('Y-m-d H:i:s', $info['rdb_last_save_time'])
                    : 'N/A',
                'changes_since_save' => $info['rdb_changes_since_last_save'] ?? 0,
            ],
            'top_commands' => $this->getTopCommands($commandStats),
        ];

        return response()->json($performance);
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    /**
     * Get top Redis commands by usage
     */
    private function getTopCommands(array $commandStats): array
    {
        $commands = [];

        foreach ($commandStats as $key => $value) {
            if (strpos($key, 'cmdstat_') === 0) {
                $cmdName = str_replace('cmdstat_', '', $key);
                // Parse: calls=X,usec=Y,usec_per_call=Z
                preg_match('/calls=(\d+),usec=(\d+),usec_per_call=([\d.]+)/', $value, $matches);

                if (count($matches) === 4) {
                    $commands[$cmdName] = [
                        'calls' => (int) $matches[1],
                        'total_time_us' => (int) $matches[2],
                        'avg_time_us' => (float) $matches[3],
                    ];
                }
            }
        }

        // Sort by call count
        uasort($commands, fn($a, $b) => $b['calls'] <=> $a['calls']);

        return array_slice($commands, 0, 10, true);
    }

    /**
     * Get Redis key patterns and their memory usage
     */
    public function keyAnalysis(): JsonResponse
    {
        $patterns = [
            'user_stats' => '{u:*}:stats',
            'user_objects' => '{u:*}:t',
            'user_categories' => '{u:*}:c',
            'user_materials' => '{u:*}:m',
            'user_brands' => '{u:*}:brands',
            'user_custom' => '{u:*}:custom',
            'user_bitmaps' => '{u:*}:up',
            'global_time_series' => '{g}:*:t',
            'geo_country' => 'c:*:t:p',
            'geo_state' => 's:*:t:p',
            'geo_city' => 'ci:*:t:p',
        ];

        $analysis = [];

        foreach ($patterns as $name => $pattern) {
            $keys = Redis::keys($pattern);
            $count = count($keys);
            $sampleSize = min(100, $count); // Sample up to 100 keys
            $totalMemory = 0;

            if ($sampleSize > 0) {
                $sample = array_rand(array_flip($keys), min($sampleSize, $count));
                $sample = is_array($sample) ? $sample : [$sample];

                foreach ($sample as $key) {
                    // Note: MEMORY USAGE requires Redis 4.0+
                    try {
                        $memory = Redis::rawCommand('MEMORY', 'USAGE', $key);
                        $totalMemory += $memory ?: 0;
                    } catch (\Exception $e) {
                        // Fallback if MEMORY USAGE not available
                        $totalMemory = 0;
                        break;
                    }
                }
            }

            $avgMemory = $sampleSize > 0 ? round($totalMemory / $sampleSize) : 0;
            $estimatedTotal = $avgMemory * $count;

            $analysis[$name] = [
                'pattern' => $pattern,
                'count' => $count,
                'avg_memory_bytes' => $avgMemory,
                'estimated_total_mb' => round($estimatedTotal / 1024 / 1024, 2),
            ];
        }

        return response()->json($analysis);
    }

    public function webIndex()
    {
        // Get your standard view data (matching your HomeController pattern)
        $auth = Auth::check();
        $user = $auth ? Auth::user() : null;
        $verified = $auth && $user->hasVerifiedEmail();
        $unsub = request()->unsub ?? null;

        // Get Redis data
        $users = $this->getUsersData(50);
        $global = $this->getGlobalData();
        $timeSeries = $this->getTimeSeriesData();
        $geo = $this->getGeoData();
        $stats = $this->getRedisStats();

        return view('admin.redis-simple', compact(
            'auth',
            'user',
            'verified',
            'unsub',
            'users',
            'global',
            'timeSeries',
            'geo',
            'stats'
        ));
    }

    public function webShow($userId)
    {
        // Get your standard view data
        $auth = Auth::check();
        $user = $auth ? Auth::user() : null;
        $verified = $auth && $user->hasVerifiedEmail();
        $unsub = request()->unsub ?? null;

        // Get user data
        $userData = User::findOrFail($userId);
        $metrics = RedisMetricsCollector::getUserCountsWithKeys($userId);
        $raw = RedisMetricsCollector::getUserCounts($userId);

        return view('admin.redis-user', compact(
            'auth',
            'user',
            'verified',
            'unsub',
            'userData',
            'metrics',
            'raw'
        ));
    }
}
