<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Alcohol extends LitterCategory
{
    protected $table = 'alcohol';

    public static function types(): array
    {
        return [
            'beerCan',
            'beerBottle',
            'spiritBottle',
            'wineBottle',
            'brokenGlass',
            'bottleTops',
            'paperCardAlcoholPackaging',
            'pint',
            'plasticAlcoholPackaging',
            'six_pack_rings',
            'alcohol_plastic_cups',
            'alcoholOther',
        ];
    }
}

