<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Suburb extends Model
{
	
    protected $fillable = [
    	'id',
    	'suburb',
    	'needles',
    	'country_id',
    	'state_id',
    	'city_id'
    ];

}
