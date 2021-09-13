<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class SoftDrinks extends LitterCategory
{
    protected $table = 'softdrinks';

    public static function types(): array
    {
        return [
            'waterBottle',
            'fizzyDrinkBottle',
            'bottleLid',
            'bottleLabel',
            'tinCan',
            'sportsDrink',
            'straws',
            'plastic_cups',
            'plastic_cup_tops',
            'milk_bottle',
            'milk_carton',
            'paper_cups',
            'pullring',
            'juice_cartons',
            'juice_bottles',
            'juice_packet',
            'ice_tea_bottles',
            'ice_tea_can',
            'energy_can',
            'strawpacket',
            'styro_cup',
            'broken_glass',
            'softDrinkOther',
        ];
    }
}
