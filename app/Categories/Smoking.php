<?php

namespace App\Categories;

use Illuminate\Database\Eloquent\Model;

class Smoking extends Model
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

    public function photo () {
    	return $this->belongsTo('App\Photo');
    }

}
