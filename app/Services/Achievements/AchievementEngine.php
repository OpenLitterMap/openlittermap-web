<?php
/**
 * OpenLitterMap – Achievement system (refactored)
 *
 * Key goals:
 *  - No facade coupling ⇒ easier to unit‑test
 *  - One Redis round‑trip per photo (pipeline)
 *  - All DSL strings compiled on boot ⇒ fail‑fast
 *  - Typed DTO for the expression context
 *  - Helpers extracted to a dedicated registrar
 */

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

class AchievementEngine
{
    /* Redis keys */
    private const KEY_USER_XP       = '{u:%d}:xp';
    private const KEY_USER_STREAK   = '{u:%d}:streak';
    private const KEY_USER_OBJECTS  = 'users:%d:totals:objects';
    private const KEY_USER_UPLOADS  = '{u:%d}:uploads';

    private const CACHE_META = 'achievement:meta';

    /** @var array<string,ParsedExpression> */
    private array $compiled;

    public function __construct(
        private CacheRepository     $cache,
        private RedisFactory        $redis,
        private ExpressionLanguage  $el = new ExpressionLanguage(),
        ?array $definitions = null,
    ) {
        $this->definitions = collect($definitions ?? config('achievements'));
        DslHelpers::register($this->el);
        $this->compiled = $this->definitions->mapWithKeys(fn($def,$slug)=>[
            $slug => $this->el->parse($def['when'], ['stats','meta','user','photo'])
        ])->all();
    }

    /* ------------------------------------------------------------------ */
    /* Public API                                                          */
    /* ------------------------------------------------------------------ */

    public function process(Photo $photo): void
    {
        $slugs = $this->slugsToUnlock($photo);
        $this->unlock($photo->user, $slugs);
    }

    public function slugsToUnlock(Photo $photo): Collection
    {
        $user  = $photo->user->loadMissing('achievements');
        $stats = $this->buildStats($user, $photo);
        $meta  = $this->cache->rememberForever(self::CACHE_META, static fn()=>[
            'total_categories'=> Category::count(),
        ]);

        return $this->definitions
            ->reject(fn($_,$slug)=>$user->achievements->where('slug',$slug)->isNotEmpty())
            ->filter(fn($_,$slug)=>$this->el->evaluate($this->compiled[$slug],[
                'stats'=>$stats,'meta'=>$meta,'user'=>$user,'photo'=>$photo]))
            ->keys();
    }

    public function unlock(User $user, Collection $slugs): void
    {
        if ($slugs->isEmpty()) {
            return;
        }

        /* -----------------------------------------------------------------
         | 1. Which achievements are new for this user?
         * -----------------------------------------------------------------*/
        // slug ➜ id   (the keys are important!)
        $idsBySlug = Achievement::whereIn('slug', $slugs)
            ->pluck('id', 'slug');

        // $user->achievements is an in-memory collection that becomes stale after the first unlock.
        // query the database on every call instead?
        $alreadyIds = $user->achievements()->pluck('achievements.id');

        // keep only the ids that are NOT already attached
        $newIds = $idsBySlug->diff($alreadyIds)->values();   // Collection<int>

        if ($newIds->isEmpty()) {
            return;   // nothing to do
        }

        // the slugs that correspond to the new ids
        $newSlugs = $idsBySlug
            ->flip()                // id ➜ slug
            ->only($newIds)         // keep only the new ids
            ->values();             // Collection<string>

        /* -----------------------------------------------------------------
         | 2. Persist pivot rows (avoids duplicates by design)
         * -----------------------------------------------------------------*/
        $user->achievements()->syncWithoutDetaching(
            $newIds->flip()->map(fn () => ['unlocked_at' => now()])->all()
        );

        /* -----------------------------------------------------------------
         | 3. Add XP for *new* achievements only
         * -----------------------------------------------------------------*/
        $addedXp = $this->definitions
            ->only($newSlugs)
            ->sum('xp');

        $this->redis->connection()->pipeline(fn ($pipe) => [
            $pipe->incrby(sprintf(self::KEY_USER_XP, $user->id), $addedXp),
            $pipe->get(sprintf(self::KEY_USER_XP, $user->id)),
        ]);

        $this->recalculateLevel($user);

        /* -----------------------------------------------------------------
         | 4. Dispatch domain event with the definitions that were unlocked
         * -----------------------------------------------------------------*/
        $defs = $this->definitions->only($newSlugs);
        event(new AchievementsUnlocked($user, $defs));
    }


    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function recalculateLevel(User $user): void
    {
        $xp = (int)$this->redis->connection()->get(sprintf(self::KEY_USER_XP,$user->id));
        $new = intdiv($xp, config('achievements.xp_per_level',1000));
        if ($new > $user->level) {
            $user->forceFill(['level'=>$new,'leveled_up_at'=>now()])->save();
        }
    }

    private function buildStats(User $user, Photo $photo): Stats
    {
        $r=$this->redis->connection();
        $uid=$user->id;
        [$xp,$uploads,$streak,$objects]=$r->pipeline(fn($p)=>[
            $p->get(sprintf(self::KEY_USER_XP,$uid)),
            $p->get(sprintf(self::KEY_USER_UPLOADS,$uid)),
            $p->get(sprintf(self::KEY_USER_STREAK,$uid)),
            $p->hgetall(sprintf(self::KEY_USER_OBJECTS,$uid)),
        ]);

        $summary     = $photo->summary ?? [];
        $tagsTree    = $summary['tags'] ?? [];
        $localObjects = [];

        foreach ($tagsTree as $maybeObjKey => $maybeObjVal) {
            // shape A: category ➜ [objectKey => {...}]
            if (\is_array($maybeObjVal) && !isset($maybeObjVal['quantity'])) {
                foreach ($maybeObjVal as $objKey => $data) {
                    $qty = (int)($data['quantity'] ?? 0);
                    $localObjects[$objKey] = ($localObjects[$objKey] ?? 0) + $qty;
                }
                continue;
            }
            // shape B: objectKey ➜ ['quantity' => n]
            $qty = (int)($maybeObjVal['quantity'] ?? 0);
            $localObjects[$maybeObjKey] = ($localObjects[$maybeObjKey] ?? 0) + $qty;
        }

        $tod = match(true){
            $photo->created_at->hour<6=>'night',
            $photo->created_at->hour<12=>'morning',
            $photo->created_at->hour<18=>'afternoon',
            default=>'evening'};

        return new Stats(
            level:$user->level,
            xp:(int)$xp,
            photosTotal:(int)$uploads,
            currentStreak:(int)$streak,
            localObjects:$localObjects,
            cumulativeObjects:$objects,
            summary:$summary,
            tod:$tod,
            dow:$photo->created_at->dayOfWeek,
        );
    }
}
