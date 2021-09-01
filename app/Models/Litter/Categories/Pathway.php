<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Pathway extends LitterCategory
{
    protected $table = 'pathways';

    protected $fillable = [
    	'gutter',
    	'gutter_long',
    	'kerb_hole_small',
    	'kerb_hole_large',
    	'pathwayOther'
    ];

    public function typesForExport(): array
    {
        return [
            'gutter' =>  'gutter',
            'gutter_long' =>  'gutter_long',
            'kerb_hole_small' =>  'kerb_hole_small',
            'kerb_hole_large' =>  'kerb_hole_large',
            'pathwayOther' =>  'pathwayOther',
        ];
    }
}
