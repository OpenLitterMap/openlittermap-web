<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Food extends LitterCategory
{
    protected $table = 'food';

    public static function types(): array
    {
        return [
            'sweetWrappers',
            'paperFoodPackaging',
            'plasticFoodPackaging',
            'plasticCutlery',
            'crisp_small',
            'crisp_large',
            'styrofoam_plate',
            'napkins',
            'sauce_packet',
            'glass_jar',
            'glass_jar_lid',
            'foodOther',
            'pizza_box',
            'aluminium_foil',
            'chewing_gum'
        ];
    }
}
