<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['xp', 'level'];
    protected $table = 'levels';
}
