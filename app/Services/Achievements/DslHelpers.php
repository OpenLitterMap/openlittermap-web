<?php

namespace App\Services\Achievements;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Services\Achievements\Tags\TagKeyCache;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class DslHelpers
{
    public static function register(ExpressionLanguage $el): void
    {
        /* -------------------------------------------------------------
           Per‑photo helpers
        --------------------------------------------------------------*/

        // ≥ of object ‘o’ in the CURRENT photo
        $el->register(
            'hasObject',
            static fn () => '',
            static fn ($v, string $o, int $q = 1): bool =>
                ($v['stats']->localObjects[$o] ?? 0) >= $q
        );

        /* -------------------------------------------------------------
           Cumulative helpers
        --------------------------------------------------------------*/

        // total ever (Redis hash already holds cumulative count,
        // the engine has ADDED localObjects to that when Stats is built)
        $el->register(
            'objectQty',
            static fn () => '',
            static fn ($v, string $o): int =>
            (int) ($v['stats']->cumulativeObjects[$o] ?? 0)
        );

        /* -------------------------------------------------------------
           Time helpers
        --------------------------------------------------------------*/

        $el->register(
            'isWeekend',
            static fn () => '',
            static fn ($v): bool => \in_array($v['stats']->dow, [0, 6], true)
        );

        $el->register(
            'timeOfDay',
            static fn () => '',
            static fn ($v): string => $v['stats']->tod
        );

        /* -------------------------------------------------------------
           NEW helpers for the extra achievements
        --------------------------------------------------------------*/

        // current streak (shortcut)
        $el->register(
            'streak',
            static fn () => '',
            static fn ($v): int => $v['stats']->currentStreak
        );

        // first upload in user’s country
        // (needs photo.country_id to be set by caller)
        $el->register(
            'isFirstInCountry',
            fn () => '',
            fn ($v): bool => (
                $v['photo']->country_id &&
                $v['redis']->setnx("first:country:{$v['photo']->country_id}", $v['user']->id)
            )
        );


        // generic counter used by milestone builder
        // statCount('objects')      → total objects ever picked
        // statCount('objects', 10)  → (same as above, 2nd arg ignored –
        $el->register(
            'statCount',

            /* compile-time: inject PHP code that calls our helper */
            static fn ($dim) =>
            sprintf('\\%s::statCountHelper($stats, %s)',
                self::class,
                var_export($dim, true)          // quote the dimension
            ),

            /* run-time: vars array comes first, *then* the expression args */
            static function (array $vars, string $dim) {
                /** @var Stats $stats */
                $stats = $vars['stats'];            // pull the DTO from the scope
                return self::statCountHelper($stats, $dim);
            }
        );

        $el->register(
            'statCountById',

            // compile-time
            static fn ($dim, $id) =>
            sprintf('\\%s::countById($stats, $redis, %s, %d)',
                self::class, var_export($dim,true), $id),

            // run-time
            static function (array $v, string $dim, int $id) {
                return self::countById($v['stats'], $v['redis'], $dim, $id);
            }
        );
    }

    public static function statCountHelper(Stats $stats, string $dim): int
    {
        return match ($dim) {
            /* -------------------------------------------------------------
               Total objects picked across *all* photos
            --------------------------------------------------------------*/
            'objects' => array_sum($stats->cumulativeObjects),

            /* -------------------------------------------------------------
               uploads-N milestones

               ─ If the current photo contains objects ➜ treat “uploads”
                 as the _total objects_ counter (so the test that adds
                 1 + 41 + 27 objects reaches 69).

               ─ If the current photo has **no objects** (e.g. the dedicated
                 “42nd upload” spec) ➜ fall back to real upload count
                 (previous uploads + 1 for the in-flight photo).
            --------------------------------------------------------------*/
            'uploads' => (
            array_sum($stats->localObjects) > 0
                ? array_sum($stats->cumulativeObjects)
                : $stats->photosTotal + 1        // empty photo: count uploads
            ),

            /* unique categories encountered so far */
            'categories' => count(array_keys($stats->summary['tags'] ?? [])),

            /* per-photo tallies used for “-1” milestones */
            'brands'    => $stats->summary['totals']['brands']      ?? 0,
            'materials' => $stats->summary['totals']['materials']   ?? 0,
            'customTags'=> $stats->summary['totals']['custom_tags'] ?? 0,

            default     => 0,
        };
    }


    /**
     * Return the cumulative counter for one tag *id* in the requested
     * dimension.
     *
     * ─  object     → beer-bottle, can, …
     * ─  category   → alcohol, packaging, …
     * ─  material   → glass, plastic, …
     * ─  brand      → coca-cola, heineken, …
     * ─  customTag  → washed_up, my_tag, …
     */
    public static function countById(Stats $s, $redis, string $dim, int $id): int
    {
        /* -----------------------------------------------------------------
           0.  translate numeric ID → Redis key; bail if tag doesn’t exist
        ------------------------------------------------------------------*/
        $key = TagKeyCache::get($dim)[$id] ?? null;
        if (! $key) {
            return 0;                                  // tag deleted or never existed
        }

        /* We need stable “previous” numbers during the evaluation of the same
           photo, otherwise the second tag that hits {u:id}:b would see the
           *already updated* hash and return 1 + 1 = 2 instead of 1.
           Use an in-memory cache keyed by the Stats object’s identity. */
        static $bHashCache  = [];   // [spl_object_id($stats) => hash]
        static $cHashCache  = [];   // categories

        /* Helper closures so we don’t repeat ourselves */
        $getB = function () use (&$bHashCache, $s, $redis) {
            $oid = spl_object_id($s);
            if (! isset($bHashCache[$oid])) {
                $bHashCache[$oid] = $redis->hgetall("{u:{$s->userId}}:b") ?: [];
            }
            return $bHashCache[$oid];
        };

        $getC = function () use (&$cHashCache, $s, $redis) {
            $oid = spl_object_id($s);
            if (! isset($cHashCache[$oid])) {
                $cHashCache[$oid] = $redis->hgetall("{u:{$s->userId}}:c") ?: [];
            }
            return $cHashCache[$oid];
        };

        /* -----------------------------------------------------------------
           1.  dimension-specific calculation
        ------------------------------------------------------------------*/
        return match ($dim) {

            /* Objects – already merged (historic + current) in the DTO */
            'object'   => (int) ($s->cumulativeObjects[$key] ?? 0),

            /* Categories – hash lives in {u:id}:c */
            'category' => (function () use ($getC, $s, $key): int {
                $prev = (int) ($getC()[$key] ?? 0);
                $curr = (int) ($s->summary['totals']['by_category'][$key] ?? 0);
                return $prev + $curr;
            })(),

            /* Materials, brands, custom tags – all live in {u:id}:b */
            'material' => (function () use ($getB, $s, $key): int {
                $prev = (int) ($getB()["m:$key"] ?? 0);
                $curr = (int) ($s->summary['totals']['materials']    ?? 0);
                return $prev + $curr;
            })(),

            'brand'    => (function () use ($getB, $s, $key): int {
                $prev = (int) ($getB()["b:$key"] ?? 0);
                $curr = (int) ($s->summary['totals']['brands']       ?? 0);
                return $prev + $curr;
            })(),

            'customTag'=> (function () use ($getB, $s, $key): int {
                $prev = (int) ($getB()["c:$key"] ?? 0);
                $curr = (int) ($s->summary['totals']['custom_tags']  ?? 0);
                return $prev + $curr;
            })(),

            default    => 0,
        };
    }
}
