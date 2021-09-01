<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Coffee extends LitterCategory
{
    protected $table = 'coffee';

    protected $fillable = [
	    'coffeeCups',
	    'coffeeLids',
	    'coffeeOther'
    ];

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'coffeeCups' => 'coffee_cup',
            'coffeeLids' => 'coffee_lid',
            'coffeeOther' => 'coffee_other',
        ];
    }
}

