<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Material extends LitterCategory
{
    protected $table = 'material';

    public static function types(): array
    {
        return [
            'aluminium',
            'bronze',
            'carbon_fiber',
            'ceramic',
            'composite',
            'concrete',
            'copper',
            'fiberglass',
            'glass',
            'iron_or_steel',
            'latex',
            'metal',
            'nickel',
            'nylon',
            'paper',
            'plastic',
            'polyethylene',
            'polymer',
            'polypropylene',
            'polystyrene',
            'pvc',
            'rubber',
            'titanium',
            'wood',
        ];
    }
}
