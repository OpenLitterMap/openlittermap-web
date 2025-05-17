<?php

namespace App\Services\Achievements;

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
    }

    public static function statCountHelper(Stats $stats, string $dim): int
    {
        return match ($dim) {
            /** total objects across *all* photos */
            'objects'     => array_sum($stats->cumulativeObjects),

            /** unique categories encountered so far */
            'categories'  => count(array_keys($stats->summary['tags'] ?? [])),

            /* uploads already stored in Redis; +1 for the current, not-yet-flushed photo */
            'uploads'     => $stats->photosTotal + 1,

            /* the current-photo tallies are good enough for the “-1” milestones.
               (You can extend Stats to fetch cumulative hashes for >1 thresholds.)
            */
            'brands'      => $stats->summary['totals']['brands']       ?? 0,
            'materials'   => $stats->summary['totals']['materials']    ?? 0,
            'customTags'  => $stats->summary['totals']['custom_tags']  ?? 0,

            default       => 0,
        };
    }
}
