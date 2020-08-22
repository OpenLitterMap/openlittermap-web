<?php

namespace App\Categories;

use Illuminate\Database\Eloquent\Model;

class Coastal extends Model
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


	// define a one to one relationship
    public function photo() {
    	return $this->hasOne('App\Photo');
    }
}
