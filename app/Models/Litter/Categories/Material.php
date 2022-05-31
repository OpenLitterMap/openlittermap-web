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
            'glass',
            'metal',
            'nylon',
            'paper',
            'plastic',
            'polystyrene',
            'wood',
        ];
    }
}
