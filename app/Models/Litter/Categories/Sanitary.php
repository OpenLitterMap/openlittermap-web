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
	    'earswabs',
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

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'condoms',
            'nappies',
            'menstral',
            'deodorant',
            'earswabs',
            'tooth_pick',
            'tooth_brush',
            'sanitaryOther',
            'gloves',
            'facemask',
            'wetwipes',
            'hand_sanitiser'
        ];
    }

}
