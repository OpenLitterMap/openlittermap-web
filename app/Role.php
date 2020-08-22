<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // fields are available for us to use
    protected $guarded = ['name', 'id'];

}
