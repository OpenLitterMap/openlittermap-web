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

    public function typesForExport(): array
    {
        return [
            'random_litter' => 'random_litter',
            'bags_litter' => 'bag_of_litter',
            'overflowing_bins' => 'overflowing_bin',
            'plastic' => 'unidentified_plastic',
            'dogshit' => 'dog_poo',
            'pooinbag' => 'dog_poo_in_a_bag',
            'automobile' => 'automobile',
            'tyre' => 'tyre',
            'traffic_cone' => 'traffic_cone',
            'dump' => 'illegal_dumping',
            'metal' => 'metal_object',
            'plastic_bags' => 'plastic_bag',
            'election_posters' => 'election_poster',
            'forsale_posters' => 'for_sale_poster',
            'cable_tie' => 'cable_tie',
            'books' => 'book',
            'magazine' => 'magazine',
            'paper' => 'paper',
            'stationary' => 'stationary',
            'washing_up' => 'washing_up',
            'clothing' => 'clothing',
            'hair_tie' => 'hair_tie',
            'ear_plugs' => 'ear_plugs_music',
            'elec_small' => 'electric_small',
            'elec_large' => 'electric_large',
            'batteries' => 'battery',
            'balloons' => 'balloon',
            'life_buoy' => 'life_buoy',
            'other' => 'other_other',
        ];
    }
}
