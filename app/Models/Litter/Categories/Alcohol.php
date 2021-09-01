<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Alcohol extends LitterCategory
{
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

    public function typesForExport(): array
    {
        return [
            'beerCan' => 'beer_can',
            'beerBottle' => 'beer_bottle',
            'spiritBottle' => 'spirit_bottle',
            'wineBottle' => 'wine_bottle',
            'brokenGlass' => 'broken_glass',
            'bottleTops' => 'beer_bottle_top',
            'paperCardAlcoholPackaging' => 'paper_card_alcohol_packaging',
            'pint' => 'pint_glass',
            'plasticAlcoholPackaging' => 'plastic_alcohol_packaging',
            'six_pack_rings' => 'six_pack_ring',
            'alcohol_plastic_cups' => 'alcohol_plastic_cup',
            'alcoholOther' => 'alcohol_other',
        ];
    }
}

