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

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'coffeeCups',
            'coffeeLids',
            'coffeeOther'
        ];
    }
}

