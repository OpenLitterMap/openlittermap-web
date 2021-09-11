<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Coffee extends LitterCategory
{
    protected $table = 'coffee';

    public function types(): array
    {
        return [
            'coffeeCups',
            'coffeeLids',
            'coffeeOther',
        ];
    }
}

