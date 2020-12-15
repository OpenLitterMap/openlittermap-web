<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Smoking extends LitterCategory
{
    protected $table = 'smoking';

    protected $fillable = [
        'id',
        'photo_id',
    	'butts',
    	'lighters',
    	'cigaretteBox',
    	'tobaccoPouch',
    	'skins',
        'smoking_plastic',
        'filters',
        'filterbox',
    	'smokingOther',
        'vape_oil',
        'vape_pen'
    ];

    /**
     * The photo related to the smoking category
     */
    public function photo () {
    	return $this->belongsTo('App\Models\Photo');
    }

    /**
     * Pre-defined litter types available on this class
     */
    public function types ()
    {
        return [
            'butts',
            'lighters',
            'cigaretteBox',
            'tobaccoPouch',
            'skins',
            'smokingOther',
            'smoking_plastic',
            'filters',
            'filterbox'
        ];
    }

}
