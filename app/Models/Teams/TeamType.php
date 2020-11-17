<?php

namespace App\Models\Teams;

use Illuminate\Database\Eloquent\Model;

class TeamType extends Model
{
    protected $casts = [
    	'price' => 'integer'
    ];
}
