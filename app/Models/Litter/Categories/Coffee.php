<?php

namespace App\Models\Litter\Categories;

use Illuminate\Database\Eloquent\Model;

class Coffee extends Model
{

    protected $table = 'coffee';

	/*
	* Only these categories can be edited in the photo model
	*/
    protected $fillable = [

    	'id',
    	'photo_id',

	    'coffeeCups',
	    'coffeeLids',
	    'coffeeOther'

    ];

    public function photo() {
    	return $this->belongsTo('App\Models\Photo');
    }

}
