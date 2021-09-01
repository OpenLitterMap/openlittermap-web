<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Industrial extends LitterCategory
{
    protected $fillable = [
    	'oil',
    	'chemical',
    	'industrial_plastic',
    	'bricks',
    	'tape',
    	'industrial_other'
    ];

    protected $table = 'industrial';

    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'oil' => 'oil',
            'chemical' => 'chemical',
            'industrial_plastic' => 'industrial_plastic',
            'bricks' => 'bricks',
            'tape' => 'tape',
            'industrial_other' => 'industrial_other',
        ];
    }
}
