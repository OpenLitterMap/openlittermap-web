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
        'juice_cartons',
        'juice_bottles',
        'juice_packet',
        'ice_tea_bottles',
        'ice_tea_can',
        'energy_can',
    	'softDrinksOther',
        'styro_cups'
    ];

    public function photo () {
    	return $this->hasOne('App\Models\Photo');
    }

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
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
            'juice_cartons',
            'juice_bottles',
            'juice_packet',
            'ice_tea_bottles',
            'ice_tea_can',
            'energy_can',
            'softDrinksOther',
            'styro_cups'
        ];
    }
}
