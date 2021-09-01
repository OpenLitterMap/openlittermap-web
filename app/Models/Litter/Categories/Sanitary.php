<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Sanitary extends LitterCategory
{
	protected $table = 'sanitary';

    protected $fillable = [
	    'condoms',
	    'nappies',
	    'menstral',
	    'deodorant',
	    'ear_swabs',
	    'tooth_pick',
	    'tooth_brush',
	    'sanitaryOther',
        'gloves',
        'facemask',
        'wetwipes',
        'hand_sanitiser'
    ];

    public function photo () {
    	return $this->hasOne('App\Models\Photo');
    }

    public function typesForExport(): array
    {
        return [
            'condoms' => 'condom',
            'nappies' => 'nappies',
            'menstral' => 'menstral',
            'deodorant' => 'deodorant',
            'ear_swabs' => 'ear_swab',
            'tooth_pick' => 'tooth_pick',
            'tooth_brush' => 'tooth_brush',
            'sanitaryOther' => 'sanitary_other',
            'gloves' => 'glove',
            'facemask' => 'facemask',
            'wetwipes' => 'wet_wipe',
            'hand_sanitiser' => 'hand_sanitiser',
        ];
    }
}
