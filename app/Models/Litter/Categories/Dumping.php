<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Dumping extends LitterCategory
{
    protected $table = 'dumping';

    public function types(): array
    {
        return [
            'small',
            'medium',
            'large',
        ];
    }
}
