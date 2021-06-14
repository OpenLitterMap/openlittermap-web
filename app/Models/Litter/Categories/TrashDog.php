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
}
