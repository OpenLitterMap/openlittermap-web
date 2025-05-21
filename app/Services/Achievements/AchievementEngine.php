<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

final class AchievementEngine
{
    /* ── Redis keys ─────────────────────────────────────────────── */
    private const K_STATS   = '{u:%d}:stats';   // hash xp, uploads, streak
    private const K_OBJECTS = '{u:%d}:t';       // hash object counts
    private const K_ACH_SET = '{u:%d}:ach';     // SET unlocked slugs

    /* cache key for meta counts */
    private const C_META = 'achievement:meta';

    /** @var array<int,array<string,bool>>  userId → slug map */
    private array $runtimeCache = [];

    /** @var Collection<string,array> */
    private Collection $definitions;
    /** @var array<string,ParsedExpression> */
    private array $compiled;
    /** cached sha for Lua */
    private static ?string $luaSha = null;

    public function __construct(
        private CacheRepository    $cache,
        private ExpressionLanguage $el = new ExpressionLanguage(),
        ?array $definitions = null,
    ) {
        /* ── 1. helpers & DSL compile cache ───────────────────── */
        DslHelpers::register($this->el);

        /* ── 2. Load static definitions ────────────────────────────── */
        $static = collect($definitions ?? config('achievements'));

        // allow tests to disable the dynamic milestones
        if (config('achievements.dynamic', true)) {
            $dynamic = $this->buildDynamicObjectMilestones()
                ->merge($this->buildLocationFirsts())
                ->merge($this->buildStreakMilestones());
            $this->definitions = $static->merge($dynamic);
        } else {
            $this->definitions = $static;
        }

        $this->compiled = $this->definitions
            ->mapWithKeys(fn($def, $slug) => [
                $slug => $this->el->parse(
                    $def['when'],
                    ['stats','meta','user','photo','redis']
                )
            ])->all();
    }

    /* ============================================================ */

    public function generateAchievements(Photo $photo): void
    {
        if ($photo->summary === null || $photo->summary === []) {
            echo "Null/empty photo.summary found, exiting. \n";
        }

        $slugs = $this->slugsToUnlock($photo);
        $this->unlock($photo->user, $slugs);
    }

    public function slugsToUnlock(Photo $photo): Collection
    {
        $user  = $photo->user;
        $stats = $this->buildStats($user, $photo);
        $meta  = $this->cache->rememberForever(self::C_META,
            fn()=>['total_categories'=>Category::count()]
        );
        $vars  = [
            'stats' => $stats,
            'meta'  => $meta,
            'user'  => $user,
            'photo' => $photo,
            'redis' => Redis::connection(),
        ];

        $owned = $user->achievements()->pluck('slug')->all();
        return collect($this->compiled)
            ->reject(fn($_,$slug) => in_array($slug, $owned, true))
            ->filter(fn($node, $slug) => $this->el->evaluate($node, $vars))
            ->keys();
    }

    public function unlock(User $user, Collection $slugs): void
    {
        $owned     = $user->achievements()->pluck('slug');
        $newSlugs  = $slugs->diff($owned);
        if ($newSlugs->isEmpty()) {
            return;
        }

        /* 1. save pivot rows (DB) ------------------------------------------------ */
        $idsBySlug  = Achievement::whereIn('slug', $newSlugs)->pluck('id', 'slug');
        $missing = $newSlugs->diff($idsBySlug->keys());

        foreach ($missing as $slug) {
            $idsBySlug[$slug] = Achievement::create([
                'slug' => $slug,
                'name' => $slug,
                'xp'   => 0,
            ])->id;
        }

        $newIds = $idsBySlug->values();
        $user->achievements()->syncWithoutDetaching(
            $newIds->flip()->map(fn()=>['unlocked_at'=>now()])->all()
        );

        /* 2. cache the slugs, then just bump the XP counter directly ------ */
        $addedXp = $this->definitions->only($newSlugs)->sum('xp');
        $redis   = Redis::connection();
        $statsKey = sprintf(self::K_STATS, $user->id);

        // make sure repeats don’t re-unlock later:
        $redis->sAdd(sprintf(self::K_ACH_SET, $user->id), ...$newSlugs);

        if ($addedXp > 0) {
            $redis->hIncrBy($statsKey, 'xp', $addedXp);
        }

        /* 3. maybe level‑up ----------------------------------------------------- */
        $this->recalculateLevel($user);

        /* 4. dispatch event ----------------------------------------------------- */
        event(new AchievementsUnlocked($user, $this->definitions->only($newSlugs)));

        /* 5. remember in-memory so we never propose them again */
        foreach ($newSlugs as $slug) {
            $this->runtimeCache[$user->id][$slug] = true;
        }
    }

    /**
     * Build the Stats DTO for a photo.
     */
    private function buildStats(User $user, Photo $photo): Stats
    {
        $r   = Redis::connection();
        $uid = $user->id;

        // -----------------------------------------------------------------
        // 1.  Core counters from Redis
        // -----------------------------------------------------------------
        [$xp, $uploads, $hashStreak] = $r->hmget(
            sprintf(self::K_STATS, $uid),
            ['xp','uploads','streak']
        );

        $stringStreak = $r->get("{u:{$uid}}:streak") ?: 0;
        $str = max((int)$hashStreak, (int)$stringStreak);

        // -----------------------------------------------------------------
        // 2.  Totals for the *current* photo
        // -----------------------------------------------------------------
        $localObjects = $photo->summary['totals']['objects'] ?? [];

        // Fallback – derive the per-object quantities from the tag tree
        if (empty($localObjects) && isset($photo->summary['tags'])) {
            foreach ($photo->summary['tags'] as $maybeCat => $maybeVal) {
                if (isset($maybeVal['quantity'])) {            // object directly
                    $localObjects[$maybeCat] = $maybeVal['quantity'];
                } else {                                       // category → objects
                    foreach ($maybeVal as $objKey => $d) {
                        $localObjects[$objKey] = ($localObjects[$objKey] ?? 0) + ($d['quantity'] ?? 0);
                    }
                }
            }
        }

        // -----------------------------------------------------------------
        // 3.  Time-of-day / day-of-week helpers
        // -----------------------------------------------------------------
        $tod = match (true) {
            $photo->created_at->hour <  6 => 'night',
            $photo->created_at->hour < 12 => 'morning',
            $photo->created_at->hour < 18 => 'afternoon',
            default                       => 'evening',
        };

        // -----------------------------------------------------------------
        // 4.  Merge cumulative object counts
        // -----------------------------------------------------------------
        $combinedObjects = array_merge_recursive(
            $r->hgetall(sprintf(self::K_OBJECTS, $uid)),      // what Redis already has
            $localObjects                                   // plus what’s in this one photo
        );
        foreach ($combinedObjects as $key => $values) {
            $combinedObjects[$key] = array_sum((array)$values);
        }

        // -----------------------------------------------------------------
        // 5.  Ensure a by_category section exists in the summary
        // -----------------------------------------------------------------
        $summary = $photo->summary;

        if (! isset($summary['tags'])) {
            $summary['tags'] = [];
        }

        if (! isset($summary['totals']['by_category'])) {
            $summary['totals']['by_category'] = [];

            foreach ($summary['tags'] as $catKey => $objs) {
                $qty = 0;
                foreach ($objs as $d) {
                    if (is_array($d) && isset($d['quantity'])) {
                        $qty += $d['quantity'];
                    }
                }
                $summary['totals']['by_category'][$catKey] = $qty;
            }
        }

        // -----------------------------------------------------------------
        // 6.  Assemble the DTO
        // -----------------------------------------------------------------
        return new Stats(
            userId            : $user->id,
            level             : $user->level,
            xp                : (int) ($xp      ?? 0),
            photosTotal       : (int) ($uploads ?? 0),
            currentStreak     : $str,
            localObjects      : $localObjects,
            cumulativeObjects : $combinedObjects,
            summary           : $summary,
            tod               : $tod,
            dow               : $photo->created_at->dayOfWeek,
        );
    }

    /* ------------------- dynamic builders (unchanged) ----------------------- */

    /**
     * Build all dynamic milestone definitions (uploads-N, objects-N,
     * object-ID-N, category-ID-N, …).
     */
    private function buildDynamicObjectMilestones(): Collection
    {
        $milestones = config('milestones');

        /* -----------------------------------------------------------------
           1.  Dimension-wide uploads-N milestones
        ------------------------------------------------------------------*/
        $uploads = collect($milestones)->mapWithKeys(function (int $m) {
            $cmp = '>=';
            return [
                "uploads-{$m}" => [
                    'xp'   => 0,
                    'when' => "statCount('uploads') {$cmp} {$m}",
                ],
            ];
        });

        /* -----------------------------------------------------------------
           2.  Dimension-wide objects-N milestones (skip N = 1 – spec starts
               at 42 and avoids a clash with per-object milestones)
        ------------------------------------------------------------------*/
        $objects = collect($milestones)
            ->reject(fn (int $m) => $m === 1)
            ->mapWithKeys(fn (int $m) => [
                "objects-{$m}" => [
                    'xp'   => 0,
                    'when' => "statCount('objects') >= {$m}",
                ],
            ]);

        /* -----------------------------------------------------------------
           3.  Per-tag milestones: object-ID-N, category-ID-N, material-ID-N,
               brand-ID-N, customTag-ID-N
        ------------------------------------------------------------------*/
        $tagIds = [
            'object'    => array_keys(TagKeyCache::get('object')),
            'category'  => array_keys(TagKeyCache::get('category')),
            'material'  => array_keys(TagKeyCache::get('material')),
            'brand'     => array_keys(TagKeyCache::get('brand')),
            'customTag' => array_keys(TagKeyCache::get('customTag')),
        ];

        $perTag = collect($tagIds)->flatMap(
            /**
             * @param  array  $ids  All tag-IDs for this dimension
             * @param  string $dim  Dimension name (object|category|material|brand|customTag)
             */
            function (array $ids, string $dim) use ($milestones) {
                return collect($ids)->flatMap(
                    function (int $id) use ($milestones, $dim) {

                        return collect($milestones)->mapWithKeys(
                            function (int $m) use ($dim, $id) {
                                $cmp  = '>=';
                                $slug = "{$dim}-{$id}-{$m}";

                                return [
                                    $slug => [
                                        'xp'   => 0,
                                        'when' => "statCountById('{$dim}', {$id}) {$cmp} {$m}",
                                    ],
                                ];
                            }
                        );
                    }
                );
            }
        );

        /* -----------------------------------------------------------------
           4.  Merge & return
        ------------------------------------------------------------------*/
        return $uploads
            ->merge($objects)
            ->merge($perTag);
    }

    private function buildLocationFirsts(): Collection          { return collect(); }

    private function buildStreakMilestones(): Collection        { return collect(); }

    /* ----------------------- level calc ------------------------------------- */
    private function recalculateLevel(User $user): void
    {
        $redis = Redis::connection();
        [$rawXp] = $redis->hmget(sprintf(self::K_STATS, $user->id), ['xp']);

        $xp = (int) ($rawXp ?? 0);

        /**
         * Level N  ==  “I have crossed N different XP thresholds”
         * - threshold list lives in config/level.php
         */
        $thresholds = array_keys(config('level'));
        sort($thresholds);                       // just in case someone edits unsorted

        // count how many thresholds the player has already reached
        $newLevel = collect($thresholds)
            ->takeWhile(fn (int $t) => $xp >= $t)
            ->count();                           // 0-based → nicely matches existing tests

        if ($newLevel > $user->level) {
            $user->level = $newLevel;
            $user->save();
        }
    }
}
