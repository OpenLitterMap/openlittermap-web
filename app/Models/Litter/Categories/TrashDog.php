<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class TrashDog extends LitterCategory
{
    protected $table = 'trashdog';

    public function types(): array
    {
        return [
            'trashdog',
            'littercat',
            'duck',
        ];
    }
}
