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
    }
}
