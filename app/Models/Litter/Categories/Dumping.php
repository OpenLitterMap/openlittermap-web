<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Dumping extends LitterCategory
{
    protected $fillable = [
    	'small',
    	'medium',
    	'large'
    ];

    protected $table = 'dumping';

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'small' => 'dumping_small',
            'medium' => 'dumping_medium',
            'large' => 'dumping_large',
        ];
    }
}
