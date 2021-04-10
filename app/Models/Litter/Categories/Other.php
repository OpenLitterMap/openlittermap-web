<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Other extends LitterCategory
{
    protected $table = 'other';

    protected $fillable = [
        'random_litter',
        'bags_litter',
        'overflowing_bins',
        'plastic',

        'dogshit', // Moved to Dogshit category
        'pooinbag',

        'automobile',
        'tyre',
        'traffic_cone',
        'dump', // Moved to dumping category
    	'metal',
        'plastic_bags',
        'election_posters',
        'forsale_posters',
        'cable_tie',
        'books',
        'magazine',
        'paper',
        'stationary',
        'washing_up',
        'clothing',
        'hair_tie',
        'ear_plugs',
        'elec_small',
        'elec_large',
        'batteries',
        'balloons',
        'life_buoy', // coastal?
        'other'
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
            'random_litter',
            'bags_litter',
            'overflowing_bins',
            'plastic',

            'dogshit',
            'pooinbag',

            'automobile',
            'tyre',
            'traffic_cone',
            'dump', // Moved to dumping category
            'metal',
            'plastic_bags',
            'election_posters',
            'forsale_posters',
            'cable_tie',
            'books',
            'magazine',
            'paper',
            'stationary',
            'washing_up',
            'clothing',
            'hair_tie',
            'ear_plugs',
            'elec_small',
            'elec_large',
            'batteries',
            'balloons',
            'life_buoy', // coastal?
            'other'
        ];
    }



}
