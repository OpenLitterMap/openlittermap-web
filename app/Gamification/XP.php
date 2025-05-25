<?php
namespace App\Gamification;

use App\Enums\Achievements\Dimension;

final class XP
{
    private const MAP = [
        Dimension::UPLOADS->value  => [1=>12, 10=>24, 69=>96, 420=>200],
        Dimension::OBJECT->value   => [1=>4 , 10=>10, 69=>40, 420=>100],
        Dimension::CATEGORY->value => [1=>6 , 10=>15, 69=>60, 420=>140],
        Dimension::MATERIAL->value => [1=>5 , 10=>12, 69=>50, 420=>120],
        Dimension::BRAND->value    => [1=>7 , 10=>18, 69=>70, 420=>160],
    ];

    public static function for(Dimension $d, int $m): int
    {
        return self::MAP[$d->value][$m] ?? 0;
    }
}
