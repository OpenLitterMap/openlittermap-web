<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomTagNew extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'custom_tags_new';
}
