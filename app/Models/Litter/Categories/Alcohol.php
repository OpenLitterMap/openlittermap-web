<?php

namespace App\Models\Litter\Categories;

use Illuminate\Database\Eloquent\Model;

class Alcohol extends Model
{
	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [
	    'beerCan',
	    'beerBottle',
	    'spiritBottle',
	    'wineBottle',
	    'brokenGlass',
	    'bottleTops',
	    'paperCardAlcoholPackaging',
	    'plasticAlcoholPackaging',
	    'six_pack_rings',
	    'alcohol_plastic_cups',
	    'alcoholOther'
    ];

	protected $table = 'alcohol';

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

}
