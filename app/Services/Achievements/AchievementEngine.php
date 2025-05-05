<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Models\Users\User;
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
        if ($slugs->isEmpty()) return;

        /* 1. save pivot rows (DB) ------------------------------------------------ */
        $idsBySlug  = Achievement::whereIn('slug',$slugs)->pluck('id','slug');
        $newIds     = $idsBySlug->values();
        $user->achievements()->syncWithoutDetaching(
            $newIds->flip()->map(fn()=>['unlocked_at'=>now()])->all()
        );

        /* 2. atomically add XP & cache slugs via Lua ---------------------------- */
        $addedXp = $this->definitions->only($slugs)->sum('xp');
        $r       = $this->redis->connection();

        self::bootLua($r);

        $statsKey = sprintf(self::K_STATS, $user->id);

        if ($addedXp > 0) { // skip Lua when nothing to add
            $r->evalSha(self::$luaSha,
                [sprintf(self::K_ACH_SET,$user->id), $statsKey],
                [$addedXp, ...$slugs->all()]
            );
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

    /* ------------------------ stats ----------------------------------------- */
    private function buildStats(User $user, Photo $photo): Stats
    {
        $r   = $this->redis->connection();
        $uid = $user->id;

        [$xp,$uploads,$streak] = $r->hmget(sprintf(self::K_STATS, $uid),'xp','uploads','st');

        $objects = $r->hgetall(sprintf(self::K_OBJECTS, $uid));

        $localObjects = $photo->summary['totals']['objects'] ?? [];

        if (empty($localObjects) && isset($photo->summary['tags'])) {
            foreach ($photo->summary['tags'] as $maybeCat => $maybeVal) {
                if (isset($maybeVal['quantity'])) {           // object directly
                    $localObjects[$maybeCat] = $maybeVal['quantity'];
                } else {                                      // category → objects
                    foreach ($maybeVal as $objKey => $d) {
                        $localObjects[$objKey] = ($localObjects[$objKey] ?? 0)
                            + ($d['quantity'] ?? 0);
                    }
                }
            }
        }

        $tod = match(true){
            $photo->created_at->hour<6  =>'night',
            $photo->created_at->hour<12 =>'morning',
            $photo->created_at->hour<18 =>'afternoon',
            default                      =>'evening',
        };

        $combinedObjects = array_merge_recursive($objects, $localObjects);
        foreach ($combinedObjects as $key => $values) {
            $combinedObjects[$key] = array_sum((array)$values);
        }

        return new Stats(
            level            : $user->level,
            xp               : (int)($xp ?? 0),
            photosTotal      : (int)($uploads ?? 0),
            currentStreak    : (int)($streak  ?? 0),
            localObjects     : $localObjects,
            cumulativeObjects: $combinedObjects,
            summary          : $photo->summary,
            tod              : $tod,
            dow              : $photo->created_at->dayOfWeek,
        );
    }

    /* ------------------- dynamic builders (unchanged) ----------------------- */

    private function buildDynamicObjectMilestones(): Collection { return collect(); }
    private function buildLocationFirsts(): Collection          { return collect(); }
    private function buildStreakMilestones(): Collection        { return collect(); }

    /* ----------------------- level calc ------------------------------------- */
    private function recalculateLevel(User $user): void
    {
        [$xp] = $this->redis->connection()->hmget(sprintf(self::K_STATS, $user->id), 'xp');

        $xp = (int)($xp ?? 0);
        $milestones = config('milestones');

        $level = 0;
        foreach ($milestones as $threshold) {
            if ($xp >= $threshold) {
                $level++;
            } else {
                break;
            }
        }

        if ($level > $user->level) {
            $user->level = $level;
            $user->save();
            $saved = $user->save();
        }
    }
}
