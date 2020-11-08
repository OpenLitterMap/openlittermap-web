<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Alcohol extends LitterCategory
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
        'pint',
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

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
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
            'alcoholOther'
        ];
    }
}

