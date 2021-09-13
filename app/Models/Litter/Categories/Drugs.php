<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Drugs extends LitterCategory
{
    public static function types(): array
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
}
