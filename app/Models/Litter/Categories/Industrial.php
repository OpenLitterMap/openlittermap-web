<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Industrial extends LitterCategory
{
    public $fillable = [
    	'oil',
    	'chemical',
    	'plastic',
    	'bricks',
    	'tape',
    	'other'
    ];

    protected $table = 'industrial';

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'oil',
            'chemical',
            'plastic',
            'bricks',
            'tape',
            'other'
        ];
    }
}
