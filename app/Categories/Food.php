<?php

namespace App\Categories;

use Illuminate\Database\Eloquent\Model;

class Food extends Model
{

    protected $table = 'food';
	/*
	* Only these categories can be edited in the photo model
	*/
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

    public function photo() {
    	return $this->hasOne('App\Photo');
    }

}
