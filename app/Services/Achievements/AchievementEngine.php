<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

final class AchievementEngine
{
    /* ───── Redis keys consistent with RedisMetricsCollector ───────── */
    private const KEY_USER_XP       = '{u:%d}:xp';
    private const KEY_USER_STREAK   = '{u:%d}:streak';
    private const KEY_USER_OBJECTS  = 'users:%d:totals:objects';
    private const KEY_USER_UPLOADS  = '{u:%d}:uploads';

    /* ───────────────────────── Cache key ──────────────────────────── */
    private const CACHE_META = 'achievement:meta';

    /* ───────────────────────── Internals ──────────────────────────── */
    private ExpressionLanguage $el;
    private Collection         $defs;                // config('achievements')
    /** @var array<string,ParsedExpression> */
    private array $compiled = [];

    public function __construct(?ExpressionLanguage $el = null)
    {
        $this->el   = $el ?? new ExpressionLanguage();
        $this->defs = collect(config('achievements'));

        $this->registerDslHelpers($this->el);
    }

    /* ───────────────────────── Public API ─────────────────────────── */

    /**
     * Call once after a photo has been saved & queued to RedisMetricsCollector.
     */
    public function process(Photo $photo): void
    {
        $slugs = $this->slugsToUnlock($photo);
        $this->unlock($photo->user, $slugs);
    }

    /* ─────────────────────── Core mechanics ──────────────────────── */

    public function slugsToUnlock(Photo $photo): Collection
    {
        $user  = $photo->user->loadMissing('achievements');
        $stats = $this->buildStats($user, $photo);

        $meta  = Cache::rememberForever(self::CACHE_META, static fn () => [
            'total_categories' => Category::count(),
            // add slow-changing global metrics here
        ]);

        return $this->defs
            ->reject(fn ($_ , $slug) => $user->achievements->where('slug', $slug)->isNotEmpty())
            ->filter(fn ($def , $slug) =>
            $this->el->evaluate($this->compiled($slug), compact('stats','meta','user','photo')))
            ->keys();
    }

    public function unlock(User $user, Collection $slugs): void
    {
        if ($slugs->isEmpty()) {
            return;
        }

        $defs  = $this->defs->only($slugs);
        $ids   = $defs->keys();
        $added = $defs->sum('xp');

        /* pivot rows – ignore duplicates raced in other workers */
        try {
            $user->achievements()->attach($ids, ['unlocked_at' => now()]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {               // not duplicate key
                Log::error('Achievement attach failed', ['msg' => $e->getMessage()]);
                throw $e;
            }
        }

        /* update XP entirely in Redis */
        Redis::incrByFloat(sprintf(self::KEY_USER_XP, $user->id), $added);
        $this->recalculateLevel($user);

        event(new AchievementsUnlocked($user, $defs));
    }

    /* ────────────────────────── Helpers ───────────────────────────── */

    private function recalculateLevel(User $user): void
    {
        $xp       = (int) Redis::get(sprintf(self::KEY_USER_XP, $user->id));
        $newLevel = intdiv($xp, 1_000);

        if ($newLevel > $user->level) {
            $user->forceFill([
                'level'         => $newLevel,
                'leveled_up_at' => now(),
            ])->save();
        }
    }

    /**
     * Build the `$stats` bag passed to every DSL expression.
     */
    private function buildStats(User $user, Photo $photo): array
    {
        $summary = $photo->summary ?? [];
        $tags    = $summary['tags'] ?? [];

        $localObjects = collect($tags)               // catKey => [objKey => data]
        ->flatten(1)                             // objKey => data
        ->mapWithKeys(
            static fn ($d, $obj) => [$obj => (int) ($d['quantity'] ?? 0)]
        );

        return [
            'level'          => $user->level,
            'xp'             => (int) Redis::get(sprintf(self::KEY_USER_XP, $user->id)),
            'photos_total'   => (int) Redis::get(sprintf(self::KEY_USER_UPLOADS, $user->id)),
            'current_streak' => (int) Redis::get(sprintf(self::KEY_USER_STREAK,  $user->id)),

            'local'          => ['objects' => $localObjects],
            'cumulative'     => ['objects' =>
                Redis::hgetall(sprintf(self::KEY_USER_OBJECTS, $user->id))],

            'summary'        => $summary,
            // context for future achievements
            'tod'            => $photo->created_at->format('H') < 6  ? 'night'
                : ($photo->created_at->format('H') < 12 ? 'morning'
                    : ($photo->created_at->format('H') < 18 ? 'afternoon' : 'evening')),
            'dow'            => $photo->created_at->dayOfWeek, // 0-6 (Sun-Sat)
        ];
    }

    /**
     * Compile (and cache) an achievement DSL string.
     */
    private function compiled(string $slug): ParsedExpression
    {
        return $this->compiled[$slug]
            ??= $this->el->parse($this->defs[$slug]['when'], ['stats','meta','user','photo']);
    }

    /**
     * Global DSL helper functions.
     */
    private function registerDslHelpers(ExpressionLanguage $el): void
    {
        $el->register(
            'hasObject',
            static fn () => '',
            static fn ($vars, string $object, int $qty = 1): bool =>
                ($vars['stats']['local']['objects'][$object] ?? 0) >= $qty
        );

        $el->register(
            'objectQty',
            static fn () => '',
            static fn ($vars, string $object): int =>
            (int) ($vars['stats']['cumulative']['objects'][$object] ?? 0)
        );

        // example helper: weekend boolean
        $el->register(
            'isWeekend',
            static fn () => '',
            static fn ($vars): bool => in_array($vars['stats']['dow'], [0, 6], true)
        );

        // example helper: morning/afternoon/evening/night
        $el->register(
            'timeOfDay',
            static fn () => '',
            static fn ($vars): string => $vars['stats']['tod']
        );
    }
}
