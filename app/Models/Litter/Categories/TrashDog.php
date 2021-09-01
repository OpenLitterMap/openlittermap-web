<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class TrashDog extends LitterCategory
{
    protected $table = 'trashdog';

    protected $fillable = [
    	'trashdog',
    	'littercat',
    	'duck'
    ];

    public function typesForExport(): array
    {
        return [
            'trashdog' => 'trashdog',
            'littercat' => 'littercat',
            'duck' => 'duck',
        ];
    }
}
