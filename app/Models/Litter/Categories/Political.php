<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Political extends LitterCategory
{
    protected $guarded = [];

    public function typesForExport(): array
    {
        return [];
    }
}
