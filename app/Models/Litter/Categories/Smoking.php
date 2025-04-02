<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Smoking extends LitterCategory
{
    protected $table = 'smoking';

    public static function types (): array
    {
        return [
            'butts', // same
            'lighters', // same
            'cigaretteBox', // now cigarette_box
            'tobaccoPouch', // same
            'skins', // now rollingPapers
            'smoking_plastic', // now packaging with materials: plastic, cellophane
            'filters', // now with materials: plastic, biodegradable
            'filterbox', // missing
            'vape_pen', // now vapePen
            'vape_oil', // now vapeOil
            'smokingOther', // now other

            // new
            // match_box
            // pipe
            // bong
            // grinder
            // ashtray
        ];
    }
}
