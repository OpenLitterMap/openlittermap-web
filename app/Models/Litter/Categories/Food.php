<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Food extends LitterCategory
{
    protected $table = 'food';

    protected $fillable = [
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
        'aluminium_foil'
    ];

    public function photo ()
    {
    	return $this->hasOne('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'sweetWrappers' => 'sweet_wrapper',
            'paperFoodPackaging' => 'paper_cardboard_food_packaging',
            'plasticFoodPackaging' => 'plastic_food_packaging',
            'plasticCutlery' => 'plastic_cutlery',
            'crisp_small' => 'crisp_small',
            'crisp_large' => 'crisp_large',
            'styrofoam_plate' => 'styrofoam_plate',
            'napkins' => 'napkin',
            'sauce_packet' => 'sauce_packet',
            'glass_jar' => 'glass_jar',
            'glass_jar_lid' => 'glass_jar_lid',
            'foodOther' => 'food_other',
            'pizza_box' => 'pizza_box',
            'aluminium_foil' => 'aluminium_foil',
        ];
    }
}
