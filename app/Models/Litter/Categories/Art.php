<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Art extends LitterCategory
{
    protected $table = 'arts';

    public function types(): array
    {
        return [
            'item',
        ];
    }
}
