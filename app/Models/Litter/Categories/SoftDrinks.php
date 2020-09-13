<?php

namespace App\Models\Litter\Categories;

use Illuminate\Database\Eloquent\Model;

class SoftDrinks extends Model
{

    protected $table = 'soft_drinks';
	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [

    	'photo_id',

    	'waterBottle',
    	'fizzyDrinkBottle',
    	'bottleLid',
    	'bottleLabel',
    	'tinCan',
    	'sportsDrink',
        'staws',
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

    public function photo() {
    	return $this->hasOne('App\Models\Photo');
    }

}
