<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Other extends LitterCategory
{
    protected $table = 'other';

    protected $fillable = [
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
        'balloons',
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

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
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
            'balloons',
            'elec_small',
            'elec_large',
            'random_litter',
            'bags_litter',
            'cable_tie',
            'tyre',
            'overflowing_bins'
        ];
    }



}
