<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Smoking extends LitterCategory
{
    protected $table = 'smoking';

    public static function types (): array
    {
        return [
            'butts',
            'lighters',
            'cigaretteBox',
            'tobaccoPouch',
            'skins',
            'smoking_plastic',
            'filters',
            'filterbox',
            'vape_pen',
            'vape_oil',
            'smokingOther',
        ];
    }

}
