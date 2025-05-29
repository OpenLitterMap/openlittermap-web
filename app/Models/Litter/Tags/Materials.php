<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Materials extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $table = 'materials';

    public $timestamps = false;
}
