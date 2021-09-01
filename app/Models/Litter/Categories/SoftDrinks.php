<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class SoftDrinks extends LitterCategory
{
    protected $table = 'softdrinks';

    protected $fillable = [
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
        'softDrinkOther'
    ];

    public function photo () {
    	return $this->hasOne('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'waterBottle' => 'plastic_water_bottle',
            'fizzyDrinkBottle' => 'plastic_fizzy_drink_bottle',
            'bottleLid' => 'softdrink_bottle_top',
            'bottleLabel' => 'bottle_label',
            'tinCan' => 'tin_can',
            'sportsDrink' => 'sports_drink',
            'straws' => 'straw',
            'plastic_cups' => 'softdrink_plastic_cup',
            'plastic_cup_tops' => 'plastic_cup_top',
            'milk_bottle' => 'milk_bottle',
            'milk_carton' => 'milk_carton',
            'paper_cups' => 'paper_cup',
            'pullring' => 'pull_ring',
            'juice_cartons' => 'juice_carton',
            'juice_bottles' => 'juice_bottle',
            'juice_packet' => 'juice_packet',
            'ice_tea_bottles' => 'ice_tea_bottle',
            'ice_tea_can' => 'ice_tea_can',
            'energy_can' => 'energy_can',
            'strawpacket' => 'straw_packaging',
            'styro_cup' => 'styrofoam_cup',
            'broken_glass' => 'broken_glass',
            'softDrinkOther' => 'softdrink_other',
        ];
    }
}
