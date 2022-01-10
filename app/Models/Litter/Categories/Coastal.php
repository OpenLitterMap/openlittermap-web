<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Coastal extends LitterCategory
{
    protected $table = 'coastal';

    public static function types(): array
    {
        return [
            'microplastics',
            'mediumplastics',
            'macroplastics',
            'rope_small',
            'rope_medium',
            'rope_large',
            'fishing_gear_nets',
            'ghost_nets',
            'buoys',
            'degraded_plasticbottle',
            'degraded_plasticbag',
            'degraded_straws',
            'degraded_lighters',
            'balloons',
            'lego',
            'shotgun_cartridges',
            'coastal_other',
            'styro_small',
            'styro_medium',
            'styro_large',
        ];
    }
}
