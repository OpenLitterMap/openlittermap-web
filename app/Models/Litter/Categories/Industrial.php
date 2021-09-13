<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Industrial extends LitterCategory
{
    protected $table = 'industrial';

    public static function types(): array
    {
        return [
            'oil',
            'chemical',
            'industrial_plastic',
            'bricks',
            'tape',
            'industrial_other',
        ];
    }
}
