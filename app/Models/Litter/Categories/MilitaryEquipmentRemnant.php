<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class MilitaryEquipmentRemnant extends LitterCategory
{
    protected $table = 'military_equipment_remnant';

    public static function types (): array
    {
        return [
            'metal_debris',
            'armoured_vehicle',
            'weapon',
        ];
    }

}
