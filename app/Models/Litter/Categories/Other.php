<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Other extends LitterCategory
{
    protected $table = 'other';

    public static function types(): array
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
            'dump',
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
            'life_buoy',
            'other',
        ];
    }
}
