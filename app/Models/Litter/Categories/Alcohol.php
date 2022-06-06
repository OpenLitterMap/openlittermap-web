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
            'cup',
            'spiritBottle',
            'wineBottle',
            'brokenGlass',
            'bottleTops',
            'packaging',
            'paperCardAlcoholPackaging',
            'pint',
            'plasticAlcoholPackaging',
            'six_pack_rings',
            'alcohol_plastic_cups',
            'alcoholOther',
        ];
    }
}

