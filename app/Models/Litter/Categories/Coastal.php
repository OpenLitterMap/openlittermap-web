<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Coastal extends LitterCategory
{
	protected $table = 'coastal';

	protected $fillable = [
		'id',
		'photo_id',
		'microplastics',
		'mediumplastics',
		'macroplastics',
		'rope_small',
		'rope_medium',
		'rope_large',
		'fishing_gear_nets',
		'buoys',
		'degraded_plasticbottle',
		'degraded_plasticbag',
		'degraded_straws',
		'degraded_lighters',
		'balloons',
		'lego',
		'shotgun_cartridges',
		'coastal_other',
		'styro_small',
		'styro_medium',
		'styro_large'
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
            'microplastics',
            'mediumplastics',
            'macroplastics',
            'rope_small',
            'rope_medium',
            'rope_large',
            'fishing_gear_nets',
            'buoys',
            'degraded_plasticbottle',
            'degraded_plasticbag',
            'degraded_straws',
            'degraded_lighters',
            'balloons',
            'lego',
            'shotgun_cartridges',
            'coastal_other',
            'styro_small',
            'styro_medium',
            'styro_large'
        ];
    }
}
