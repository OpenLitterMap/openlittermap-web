<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Dumping extends LitterCategory
{
    public $fillable = [
    	'small',
    	'medium',
    	'large'
    ];

    protected $table = 'dumping';

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'small',
            'medium',
            'large'
        ];
    }
}
