<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Ordnance extends LitterCategory
{
    protected $table = 'ordnance';

    public static function types (): array
    {
        return [
            'land_mine',
            'missile',
            'grenade',
            'shell',
            'other'
        ];
    }

}
