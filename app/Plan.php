<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $casts = [
    	'price' => 'integer'
    ];

}
