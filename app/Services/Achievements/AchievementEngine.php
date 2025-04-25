<?php

namespace App\Services\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Litter\Tags\Category;
use App\Models\Users\User;
use App\Models\Photo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class AchievementEngine
{
    /* ─────────────────────────  Redis key patterns  ────────────────────────── */
    private const KEY_USER_OBJECTS  = 'users:%d:totals:objects';  // hash (object_key=>qty)
    private const KEY_USER_ACTIVITY = 'activity:users:%d';        // hash (YYYY-MM-DD=>uploads)
    private const CACHE_GLOBAL_META = 'achievement:meta';

    /* ─────────────────────────  Internals  ─────────────────────────────────── */
    private ExpressionLanguage $el;

    public function __construct()
    {
        $this->el = new ExpressionLanguage;
        $this->registerDslHelpers($this->el);
    }

    /* ─────────────────────────  Public API  ─────────────────────────────────── */

    /**
     * Determine which achievement slugs should unlock after a photo is saved.
     */
    public function slugsToUnlock(Photo $photo): Collection
    {
        $user  = $photo->user;                             // eager-loaded in your flow
        $stats = $this->buildStats($user, $photo);         // per-user / per-photo data
        $meta  = Cache::rememberForever(self::CACHE_GLOBAL_META, static fn () => [
            'total_categories' => Category::count(),
        ]);

        return collect(config('achievements'))
            ->reject(fn ($_,$slug) => $user->achievements->contains('slug', $slug))
            ->filter(fn ($def) => $this->el->evaluate(
                $def['when'], compact('stats','meta','user','photo')
            ))
            ->keys();
    }

    /**
     * Persist unlocks, award XP & fire the AchievementsUnlocked event.
     */
    public function unlock(User $user, Collection $slugs): void
    {
        if ($slugs->isEmpty()) {
            return;
        }

        $defs = collect(config('achievements'))->only($slugs);

        // attach pivot rows (timestamps handled by relationship)
        $user->achievements()->attach($defs->keys(), [
            'unlocked_at' => now(),
        ]);

        // XP & level-up
        $user->increment('xp', $defs->sum('xp'));
        $this->recalculateLevel($user);

        // broadcast
        event(new AchievementsUnlocked($user, $defs));
    }

    /* ─────────────────────────  Helpers  ────────────────────────────────────── */

    private function recalculateLevel(User $user): void
    {
        $newLevel = intdiv($user->xp, 1_000);
        if ($newLevel > $user->level) {
            $user->forceFill([
                'level'         => $newLevel,
                'leveled_up_at' => now(),
            ])->save();
        }
    }

    /**
     * Build the $stats array passed to DSL expressions.
     *
     *  stats = [
     *      'level'          => int,
     *      'photos_total'   => int,
     *      'current_streak' => int,
     *      'local'      => ['objects' => [obj_key => qty, …]],
     *      'cumulative' => ['objects' => [obj_key => qty, …]],
     *      'summary'    => (photo.summary JSON),
     * ]
     */
    private function buildStats(User $user, Photo $photo): array
    {
        $summary = $photo->summary ?? [];
        $tags    = $summary['tags'] ?? [];

        // quantities in THIS photo
        $localObjects = collect($tags)->map(fn ($d) => $d['quantity'])->all();

        // cumulative object totals (Redis hash)
        $cumObjects = Redis::hgetall(sprintf(self::KEY_USER_OBJECTS, $user->id));

        return [
            'level'          => $user->level,
            'photos_total'   => $user->photos()->count(),         // quick COUNT(*)
            'current_streak' => $this->currentStreak($user->id),
            'local'      => ['objects' => $localObjects],
            'cumulative' => ['objects' => $cumObjects ],
            'summary'    => $summary,
        ];
    }

    /**
     * Consecutive-day streak based on Redis activity hash.
     */
    private function currentStreak(int $userId): int
    {
        $hash   = Redis::hgetall(sprintf(self::KEY_USER_ACTIVITY, $userId));
        $streak = 0;
        for ($i = 0; ; $i++) {
            $date = now()->subDays($i)->toDateString();
            if (! isset($hash[$date])) {
                break;
            }
            $streak++;
        }
        return $streak;
    }

    /**
     * Register helper functions available inside the DSL `when` strings.
     */
    private function registerDslHelpers(ExpressionLanguage $el): void
    {
        // hasObject("plastic_bottle", 5)
        $el->register(
            'hasObject',
            static fn ($obj,$qty=1) => '',
            static fn ($vars,$obj,$qty=1) =>
                ($vars['stats']['local']['objects'][$obj] ?? 0) >= $qty
        );

        // objectQty("plastic_bottle")
        $el->register(
            'objectQty',
            static fn ($obj) => '',
            static fn ($vars,$obj) =>
            (int) ($vars['stats']['cumulative']['objects'][$obj] ?? 0)
        );
    }
}
