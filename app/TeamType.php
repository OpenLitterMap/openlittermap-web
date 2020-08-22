<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TeamType extends Model
{
    protected $casts = [
    	'price' => 'integer'
    ];
}
