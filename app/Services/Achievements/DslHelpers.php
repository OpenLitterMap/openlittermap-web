<?php

namespace App\Services\Achievements;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DslHelpers
{
    public static function register(ExpressionLanguage $el): void
    {
        $el->register('hasObject',
            static fn() => '',
            static fn($v, string $o, int $q = 1) =>
            (($v['stats']->localObjects[$o] ?? 0) >= $q)
        );

        // ←– change this one:
        $el->register('objectQty',
            static fn() => '',
            static fn($v, string $o): int =>
                // cumulative (from Redis) + local (from this photo)
                (int)($v['stats']->cumulativeObjects[$o] ?? 0)
                + (int)($v['stats']->localObjects[$o] ?? 0)
        );

        $el->register('isWeekend',
            static fn() => '',
            static fn($v): bool => in_array($v['stats']->dow, [0, 6], true)
        );
        $el->register('timeOfDay',
            static fn() => '',
            static fn($v): string => $v['stats']->tod
        );
    }

}
