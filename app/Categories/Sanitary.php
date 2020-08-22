<?php

namespace App\Categories;

use Illuminate\Database\Eloquent\Model;

class Sanitary extends Model
{
	protected $table = 'sanitary';
	
	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [
    	'id',
    	'photo_id',
	    'condoms',
	    'nappies',
	    'menstral',
	    'deodorant',
	    'earswabs',
	    'tooth_pick',
	    'tooth_brush',
	    'sanitaryOther',
        'gloves',
        'facemask'
    ];

    public function photo () {
    	return $this->hasOne('App\Photo');
    }

}
