<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Pathway extends LitterCategory
{
    protected $table = 'pathways';

    public static function types(): array
    {
        return [
            'gutter',
            'gutter_long',
            'kerb_hole_small',
            'kerb_hole_large',
            'pathwayOther',
        ];
    }
}
