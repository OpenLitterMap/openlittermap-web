<?php

namespace App\Models\Litter\Categories;

use App\Models\Litter\LitterCategory;

class Coastal extends LitterCategory
{
	protected $table = 'coastal';

	protected $fillable = [
		'microplastics',
		'mediumplastics',
		'macroplastics',
		'rope_small',
		'rope_medium',
		'rope_large',
		'fishing_gear_nets',
        'ghost_nets',
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

    public function typesForExport(): array
    {
        return [
            'microplastics' => 'microplastic',
            'mediumplastics' => 'mediumplastic',
            'macroplastics' => 'macroplastic',
            'rope_small' => 'rope_small',
            'rope_medium' => 'rope_medium',
            'rope_large' => 'rope_large',
            'fishing_gear_nets' => 'fishing_gear_net',
            'ghost_nets' => 'ghost_nets',
            'buoys' => 'buoy',
            'degraded_plasticbottle' => 'degraded_plastic_bottle',
            'degraded_plasticbag' => 'degraded_plastic_bag',
            'degraded_straws' => 'degraded_straw',
            'degraded_lighters' => 'degraded_lighter',
            'balloons' => 'coastal_balloon',
            'lego' => 'lego',
            'shotgun_cartridges' => 'shotgun_cartridge',
            'coastal_other' => 'coastal_other',
            'styro_small' => 'styrofoam_small',
            'styro_medium' => 'styrofoam_medium',
            'styro_large' => 'styrofoam_large',
        ];
    }
}
