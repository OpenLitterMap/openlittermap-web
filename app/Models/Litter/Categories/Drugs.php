<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Drugs extends LitterCategory
{
    protected $fillable = [
        'needles',
        'wipes',
        'tops',
        'packaging',
        'waterbottle',
        'spoons',
        'needlebin',
        'usedtinfoil',
        'barrels',
        'fullpackage',
        'baggie',
        'crack_pipes',
        'drugsOther'
    ];

    // define a one to one relationship
    public function photo()
    {
        return $this->hasOne('App\Models\Photo');
    }

    public function types(): array
    {
        return [
            'needles',
            'wipes',
            'tops',
            'packaging',
            'waterbottle',
            'spoons',
            'needlebin',
            'usedtinfoil',
            'barrels',
            'fullpackage',
            'baggie',
            'crack_pipes',
            'drugsOther'
        ];
    }

    public function typesForExport(): array
    {
        return [];
    }
}
