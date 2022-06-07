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
            'packaging',
            'paperFoodPackaging',
            'plasticFoodPackaging',
            'cutlery',
            'plasticCutlery',
            'crisp_small',
            'crisp_large',
            'styrofoam_plate',
            'napkins',
            'sauce_packet',
            'jar',
            'glass_jar',
            'jar_lid',
            'glass_jar_lid',
            'foodOther',
            'pizza_box',
            'foil',
            'aluminium_foil',
            'chewing_gum'
        ];
    }
}
