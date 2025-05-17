<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

final class AchievementEngine
{
    /* ── Redis keys ─────────────────────────────────────────────── */
    private const K_STATS   = '{u:%d}:stats';   // hash xp, uploads, streak
    private const K_OBJECTS = '{u:%d}:t';       // hash object counts
    private const K_ACH_SET = '{u:%d}:ach';     // SET unlocked slugs

    /* cache key for meta counts */
    private const C_META = 'achievement:meta:v2';

    /** @var Collection<string,array> */
    private Collection $definitions;
    /** @var array<string,ParsedExpression> */
    private array $compiled;
    /** cached sha for Lua */
    private static ?string $luaSha = null;

    public function __construct(
        private CacheRepository    $cache,
        private RedisFactory       $redis,
        private ExpressionLanguage $el = new ExpressionLanguage(),
        ?array $definitions = null,
    ) {
        /* ── 1. helpers & DSL compile cache ───────────────────── */
        DslHelpers::register($this->el);

        // enable Symfony cache for parsed expressions → compiled once ‑> APCu
        // cache compiled DSL if the EL version supports it
        if (method_exists($this->el, 'setCacheItemPool')) {
            $this->el->setCacheItemPool(
                app('cache')->store('array')->getPool()
            );
        }

        /* ── 2. load achievement definitions (static + dynamic) ─ */
        $static  = collect($definitions ?? config('achievements'));
        $dynamic = $this->buildDynamicObjectMilestones()
            ->merge($this->buildLocationFirsts())
            ->merge($this->buildStreakMilestones());

        $this->definitions = $static->merge($dynamic);

        // compile DSL
        $this->compiled = $this->definitions->mapWithKeys(
            fn($d,$slug)=>[$slug=>$this->el->parse($d['when'],
                ['stats','meta','user','photo','redis']
            )]
        )->all();
    }

    /* ============================================================ */

    public function process(Photo $photo): void
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

        /* filter with Redis set cache instead of SQL */
        $achSetKey = sprintf(self::K_ACH_SET, $user->id);
        $r         = $this->redis->connection();

        return $this->definitions
            ->reject(fn($_,$slug)=> $r->sIsMember($achSetKey, $slug))
            ->filter(fn($def,$slug)=>
            $this->el->evaluate($this->compiled[$slug],[
                'stats'=>$stats,'meta'=>$meta,'user'=>$user,'photo'=>$photo,'redis'=>$r
            ])
            )
            ->keys();
    }

    public function unlock(User $user, Collection $slugs): void
    {
        if ($slugs->isEmpty()) {
            return;
        }

        /* 1. save pivot rows (DB) ------------------------------------------------ */
        $idsBySlug  = Achievement::whereIn('slug',$slugs)->pluck('id','slug');

        $missing = $slugs->diff($idsBySlug->keys());

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

        /* 2. atomically add XP & cache slugs via Lua ---------------------------- */
        $addedXp = $this->definitions->only($slugs)->sum('xp');
        $redis   = $this->redis->connection();

        self::bootLua($redis);

        $statsKey = sprintf(self::K_STATS, $user->id);

        if ($addedXp > 0) { // skip Lua when nothing to add
            $keys = [sprintf(self::K_ACH_SET, $user->id), $statsKey];
            $args = [$addedXp, ...$slugs->all()];

            $redis->evalSha(self::$luaSha, count($keys), ...array_merge($keys, $args));
        }

        /* 3. maybe level‑up ----------------------------------------------------- */
        $this->recalculateLevel($user);

        /* 4. dispatch event ----------------------------------------------------- */
        event(new AchievementsUnlocked($user, $this->definitions->only($slugs)));
    }

    /* ============================================================ */

    private static function bootLua($redis): void
    {
        if (!self::$luaSha) {
            $script = file_get_contents(
                base_path('app/Services/Achievements/lua/xp_add.lua')
            );
            self::$luaSha = $redis->script('LOAD', $script);
        }
    }

    /**
     * Build the Stats DTO for a photo.
     */
    private function buildStats(User $user, Photo $photo): Stats
    {
        $r   = $this->redis->connection();
        $uid = $user->id;

        // -----------------------------------------------------------------
        // 1.  Core counters from Redis
        // -----------------------------------------------------------------
        [$xp, $uploads, $streak] = $r->hmget(
            sprintf(self::K_STATS, $uid),
            'xp', 'uploads', 'st'
        );

        // object totals already stored in Redis
        $objects = $r->hgetall(sprintf(self::K_OBJECTS, $uid));

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
                        $localObjects[$objKey] = ($localObjects[$objKey] ?? 0)
                            + ($d['quantity'] ?? 0);
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
        $combinedObjects = array_merge_recursive($objects, $localObjects);
        foreach ($combinedObjects as $key => $values) {
            $combinedObjects[$key] = array_sum((array) $values);
        }

        // -----------------------------------------------------------------
        // 5.  Ensure a by_category section exists in the summary
        //     (work on a local copy – never mutate Eloquent attributes)
        // -----------------------------------------------------------------
        $summary = $photo->summary;                    // shallow copy

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
            currentStreak     : (int) ($streak  ?? 0),
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
        $milestones = config('milestones');         // e.g. [1, 42, 69]

        /* -----------------------------------------------------------------
           1.  Dimension-wide uploads-N milestones
        ------------------------------------------------------------------*/
        $uploads = collect($milestones)->mapWithKeys(
            fn (int $m) => [
                "uploads-{$m}" => [
                    'xp'   => 0,
                    'when' => "statCount('uploads') == {$m}",
                ],
            ]
        );

        /* -----------------------------------------------------------------
           2.  Dimension-wide objects-N milestones (skip N = 1 – spec starts
               at 42 and avoids a clash with per-object milestones)
        ------------------------------------------------------------------*/
        $objects = collect($milestones)
            ->reject(fn (int $m) => $m === 1)
            ->mapWithKeys(fn (int $m) => [
                "objects-{$m}" => [
                    'xp'   => 0,
                    'when' => "statCount('objects') == {$m}",
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
            fn (array $ids, string $dim) => collect($ids)->flatMap(
                fn (int $id)             => collect($milestones)->mapWithKeys(
                    function (int $m) use ($dim, $id) {
                        $slug = "{$dim}-{$id}-{$m}";
                        return [
                            $slug => [
                                'xp'   => 0,
                                'when' => "statCountById('{$dim}', {$id}) == {$m}",
                            ],
                        ];
                    }
                )
            )
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
    /* ----------------------- level calc ------------------------------------- */
    private function recalculateLevel(User $user): void
    {
        [$rawXp] = $this->redis->connection()
            ->hmget(sprintf(self::K_STATS, $user->id), 'xp');

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
