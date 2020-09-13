<?php

namespace App\Models\Litter\Categories;

use Illuminate\Database\Eloquent\Model;

class TrashDog extends Model
{
    protected $fillable = [
    	'photo_id',
    	'trashdog',
    	'littercat',
    	'duck'
    ];
}
