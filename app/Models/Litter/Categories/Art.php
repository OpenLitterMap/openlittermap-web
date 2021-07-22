<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Art extends LitterCategory
{
    protected $table = 'arts';

    protected $fillable = [
        'item'
    ];

    public function types()
    {
        return [
            'item'
        ];
    }
}
