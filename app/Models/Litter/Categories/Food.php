<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Food extends LitterCategory
{
    protected $table = 'food';

    protected $fillable = [
        'id',
    	'photo_id',

	    'sweetWrappers',
    	'cardboardFoodPackaging',
    	'paperFoodPackaging',
    	'plasticFoodPackaging',
    	'plasticCutlery',
        'crisp_small',
        'crisp_large',
        'styrofoam_plate',
        'napkins',
        'sauce_plate',
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

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'sweetWrappers',
            'cardboardFoodPackaging',
            'paperFoodPackaging',
            'plasticFoodPackaging',
            'plasticCutlery',
            'crisp_small',
            'crisp_large',
            'styrofoam_plate',
            'napkins',
            'sauce_plate',
            'glass_jar',
            'glass_jar_lid',
            'foodOther',
            'pizza_box',
            'aluminium_foil'
        ];
    }

}
