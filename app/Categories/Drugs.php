<?php

namespace App\Categories;

use Illuminate\Database\Eloquent\Model;

class Drugs extends Model
{
	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [
        'id',
    	'photo_id',
    	'needles', 
    	'wipes', 
    	'tops', 
    	'packaging', 
    	'waterbottle', 
    	'spoons', 
    	'needlebin', 
    	'usedtinfoil', 
    	'barrels', 
    	'fullpackage',
        'baggie',
        'crack_pipes',
        'drugsOther'

    ];

    // define a one to one relationship
    public function photo() {
    	return $this->hasOne('App\Photo');
    }



}
