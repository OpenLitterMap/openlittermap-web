<?php

namespace App\Models\Litter\Categories;

use Illuminate\Database\Eloquent\Model;

class Other extends Model
{

    protected $table = 'others';

	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [
        'id',
    	'dogshit',
    	'plastic',
        'dump',
    	'metal',
        'plastic_bag',
        'election_posters',
        'forsale_posters',
        'books',
        'magazines',
        'paper',
        'stationary',
        'washing_up',
        'hair_tie',
        'ear_plugs',
    	'other',
        'batteries',
        'elec_small',
        'elec_large',
        'random_litter',
        'bags_litter',
        'cable_tie',
        'tyre',
        'overflowing_bins'
    ];

    public function photo () {
    	return $this->hasOne('App\Models\Photo');
    }

}
