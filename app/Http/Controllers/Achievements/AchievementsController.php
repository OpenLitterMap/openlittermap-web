<?php

namespace App\Http\Controllers\Achievements;

use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\Category;
use App\Services\Achievements\AchievementRepository;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementsController extends Controller
{
    private const ALLOWED_TYPES = [
        'uploads',
        'streak',
        'categories',
        'category',
        'objects',
        'object',
    ];

    public function __construct(private AchievementRepository $repository) {}

    /**
     * Return hierarchically organized achievements
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // \Log::info('User counts from Redis', RedisMetricsCollector::getUserCounts($userId));

        // Get cached data
        $unlockedIds = $this->repository->getUnlockedAchievementIds($userId);
        $counts = RedisMetricsCollector::getUserCounts($userId);

        // Build hierarchical response
        $response = [
            'overview' => $this->buildOverview($unlockedIds, $counts),
            'categories' => $this->buildCategoriesWithObjects($unlockedIds, $counts),
            'summary' => $this->buildSummary($userId)
        ];

        return response()->json($response);
    }

    /**
     * Build overview section (dimension-wide achievements)
     */
    private function buildOverview(array $unlockedIds, array $counts): array
    {
        return [
            'uploads' => $this->getDimensionProgress('uploads', null, $unlockedIds, $counts),
            'streak' => $this->getDimensionProgress('streak', null, $unlockedIds, $counts),
            'total_categories' => $this->getDimensionProgress('categories', null, $unlockedIds, $counts),
            'total_objects' => $this->getDimensionProgress('objects', null, $unlockedIds, $counts),
        ];
    }

    /**
     * Build categories with nested objects
     */
    private function buildCategoriesWithObjects(array $unlockedIds, array $counts): array
    {
        // Get all categories with their objects
        $categories = Category::where('crowdsourced', false)
            ->with(['litterObjects'])
            ->get();

        $result = [];

        foreach ($categories as $category) {
            $categoryData = [
                'id' => $category->id,
                'key' => $category->key,
                'name' => $category->name ?? $category->key,
                'achievement' => $this->getDimensionProgress('category', $category->id, $unlockedIds, $counts),
                'objects' => []
            ];

            // Add nested objects
            foreach ($category->litterObjects as $object) {
                $categoryData['objects'][] = [
                    'id' => $object->id,
                    'key' => $object->key,
                    'name' => $object->name ?? $object->key,
                    'achievement' => $this->getDimensionProgress('object', $object->id, $unlockedIds, $counts)
                ];
            }

            // Sort objects by progress (highest first) then by name
            usort($categoryData['objects'], function ($a, $b) {
                $progressDiff = $b['achievement']['progress'] - $a['achievement']['progress'];
                if ($progressDiff !== 0) {
                    return $progressDiff;
                }
                return strcmp($a['name'], $b['name']);
            });

            $result[] = $categoryData;
        }

        // Sort categories by progress (highest first) then by name
        usort($result, function ($a, $b) {
            $progressDiff = $b['achievement']['progress'] - $a['achievement']['progress'];
            if ($progressDiff !== 0) {
                return $progressDiff;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Get progress for a specific dimension/tag combination
     */
    private function getDimensionProgress(string $type, ?int $tagId, array $unlockedIds, array $counts): array
    {
        // Get all achievements for this type/tag combo
        $query = DB::table('achievements')->where('type', $type);

        if ($tagId !== null) {
            $query->where('tag_id', $tagId);
        } else {
            $query->whereNull('tag_id');
        }

        $achievements = $query->orderBy('threshold')->get();

        if ($achievements->isEmpty()) {
            return [
                'progress' => 0,
                'next_threshold' => null,
                'percentage' => 0,
                'unlocked' => [],
                'next' => null
            ];
        }

        // Get current progress
        $progress = $this->getProgress($type, $tagId, $counts);

        // Find unlocked and next
        $unlocked = [];
        $next = null;

        foreach ($achievements as $achievement) {
            if (in_array($achievement->id, $unlockedIds, true)) {
                $unlocked[] = [
                    'id' => $achievement->id,
                    'threshold' => $achievement->threshold,
                    'metadata' => is_string($achievement->metadata)
                        ? json_decode($achievement->metadata, true)
                        : $achievement->metadata
                ];
            } elseif ($next === null) {
                // First locked achievement is the next target
                $next = [
                    'id' => $achievement->id,
                    'threshold' => $achievement->threshold,
                    'metadata' => is_string($achievement->metadata)
                        ? json_decode($achievement->metadata, true)
                        : $achievement->metadata,
                    'percentage' => min(100, (int) round(($progress / $achievement->threshold) * 100))
                ];
            }
        }

        return [
            'progress' => $progress,
            'next_threshold' => $next ? $next['threshold'] : null,
            'percentage' => $next ? $next['percentage'] : 100,
            'unlocked' => $unlocked,
            'next' => $next
        ];
    }

    /**
     * Get progress value for specific type/tag
     */
    private function getProgress(string $type, ?int $tagId, array $counts): int
    {
        return match ($type) {
            'uploads' => (int) ($counts['uploads'] ?? 0),
            'streak' => (int) ($counts['streak'] ?? 0),
            'object' => (int) ($counts['objects'][$tagId ?? ''] ?? 0),
            'objects' => array_sum($counts['objects'] ?? []),
            'category' => (int) ($counts['categories'][$tagId ?? ''] ?? 0),
            'categories' => array_sum($counts['categories'] ?? []),
            default => 0,
        };
    }

    /**
     * Build summary statistics
     */
    private function buildSummary(int $userId): array
    {
        $total = DB::table('achievements')
            ->whereIn('type', self::ALLOWED_TYPES)
            ->count();

        $unlockedTotal = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $userId)
            ->whereIn('achievements.type', self::ALLOWED_TYPES)
            ->count();

        return [
            'total' => $total,
            'unlocked' => $unlockedTotal,
            'percentage' => $total > 0 ? (int) round(($unlockedTotal / $total) * 100) : 0,
        ];
    }
}
