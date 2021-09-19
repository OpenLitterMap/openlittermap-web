<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Sanitary extends LitterCategory
{
    protected $table = 'sanitary';

    public static function types(): array
    {
        return [
            'condoms',
            'nappies',
            'menstral',
            'deodorant',
            'ear_swabs',
            'tooth_pick',
            'tooth_brush',
            'sanitaryOther',
            'gloves',
            'facemask',
            'wetwipes',
            'hand_sanitiser',
        ];
    }
}
