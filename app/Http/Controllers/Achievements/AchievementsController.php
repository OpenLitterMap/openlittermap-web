<?php

namespace App\Http\Controllers\Achievements;

use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Services\Achievements\AchievementRepository;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementsController extends Controller
{
    private const ALLOWED_TYPES = [
        'uploads',       // dimension‑wide – no tag_id
        'streak',        // dimension‑wide – no tag_id
        'category',
        'categories',
        'object',
        'objects',
    ];

    public function __construct(private AchievementRepository $repository) {}

    /**
     * Return all unlocked achievements for the user **plus** the next one
     * that can be unlocked per (dimension, tag) pair.
     *
     * Excludes Brands, Materials and CustomTags – those will be brought in later.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // 1) caches ----------------------------------------------------------
        $unlockedIds = $this->repository->getUnlockedAchievementIds($userId);
        $counts      = RedisMetricsCollector::getUserCounts($userId); // fast – hits Redis

        // 2) build response ---------------------------------------------------
        $achievements = $this->buildAchievements($unlockedIds, $counts);

        // 3) overall summary --------------------------------------------------
        $total = DB::table('achievements')
            ->whereIn('type', self::ALLOWED_TYPES)
            ->count();

        // Count only unlocked achievements of allowed types
        $unlockedTotal = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $userId)
            ->whereIn('achievements.type', self::ALLOWED_TYPES)
            ->count();

        return response()->json([
            'achievements' => $achievements,
            'summary'      => [
                'total'      => $total,
                'unlocked'   => $unlockedTotal,
                'percentage' => $total > 0 ? (int) round(($unlockedTotal / $total) * 100) : 0,
            ],
        ]);
    }

    /**
     * Build nested achievement array grouped by type.
     */
    private function buildAchievements(array $unlockedIds, array $counts): array
    {
        $result = [];

        // Grab *all* relevant achievement definitions up‑front to avoid N+1s.
        $definitions = DB::table('achievements')
            ->whereIn('type', self::ALLOWED_TYPES)
            ->orderBy('type')
            ->orderByRaw('COALESCE(tag_id, 0)')
            ->orderBy('threshold')
            ->get();

        // Group by (type, tag_id)
        $definitions->groupBy(function ($def) {
            // Use 'null' string for dimension‑wide to keep array keys consistent
            return $def->type . '|' . ($def->tag_id ?? 'null');
        })->each(function ($group) use (&$result, $unlockedIds, $counts) {
            /** @var \Illuminate\Support\Collection $group */
            $first       = $group->first();
            $type        = $first->type;
            $tagId       = $first->tag_id; // may be null
            $progress    = $this->getProgress($type, $tagId, $counts);
            $entries     = [];
            $addedNext   = false;

            foreach ($group as $def) {
                $isUnlocked = \in_array($def->id, $unlockedIds, true);

                if ($isUnlocked) {
                    $entries[] = $this->toPayload($def, $progress, true);
                    continue;
                }

                if (! $addedNext) {
                    // First locked – that's the "next" target
                    $entries[] = $this->toPayload($def, $progress, false);
                    $addedNext = true;
                }

                // No more locked achievements after the first one.
                if ($addedNext) {
                    break;
                }
            }

            if (!empty($entries)) {
                if (!isset($result[$type])) {
                    $result[$type] = [];
                }
                array_push($result[$type], ...$entries);
            }
        });

        return $result;
    }

    /**
     * Convert DB row → API payload.
     */
    private function toPayload(object $def, int $progress, bool $unlocked): array
    {
        return [
            'id'         => $def->id,
            'type'       => $def->type,
            'tag_id'     => $def->tag_id,
            'threshold'  => $def->threshold,
            'metadata'   => is_string($def->metadata) ? json_decode($def->metadata, true) : $def->metadata,
            'unlocked'   => $unlocked,
            'progress'   => $progress,
            'percentage' => min(100, (int) round(($progress / $def->threshold) * 100)),
            'tag_name'   => $def->tag_id ? $this->getTagName($def->type, (int) $def->tag_id) : null,
        ];
    }

    /**
     * Map dimension → user progress value.
     */
    private function getProgress(string $type, ?int $tagId, array $c): int
    {
        return match ($type) {
            'uploads'   => (int) ($c['uploads'] ?? 0),
            'streak'    => (int) ($c['streak']  ?? 0),
            'object', 'objects'     => (int) ($c['objects'][$tagId ?? '']    ?? 0),
            'category', 'categories' => (int) ($c['categories'][$tagId ?? ''] ?? 0),
            default     => 0,
        };
    }

    /**
     * Fetch display key for category / object.
     */
    private function getTagName(string $type, int $tagId): ?string
    {
        return match ($type) {
            'object', 'objects'     => LitterObject::where('id', $tagId)->value('key'),
            'category', 'categories' => Category::where('id', $tagId)->value('key'),
            default                 => null,
        };
    }
}
