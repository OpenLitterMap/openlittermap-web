<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Smoking extends LitterCategory
{
    protected $table = 'smoking';

    protected $fillable = [
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
    public function photo ()
    {
    	return $this->belongsTo('App\Models\Photo');
    }

    public function typesForExport (): array
    {
        return [
            'butts' => 'cigarette_butt',
            'lighters' => 'lighter',
            'cigaretteBox' => 'cigarette_box',
            'tobaccoPouch' => 'tobacco_pouch',
            'skins' => 'rolling_paper',
            'smoking_plastic' => 'plastic_smoking_packaging',
            'filters' => 'filter',
            'filterbox' => 'filterbox',
            'vape_pen' => 'vape_pen',
            'vape_oil' => 'vape_oil',
            'smokingOther' => 'smoking_other',
        ];
    }

}
