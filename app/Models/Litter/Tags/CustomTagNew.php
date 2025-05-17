<?php

namespace App\Models\Litter\Tags;

use App\Models\Litter\Tags\Traits\InvalidatesTagKeyCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomTagNew extends Model
{
    use HasFactory;
    use InvalidatesTagKeyCache;

    protected $guarded = [];

    protected $table = 'custom_tags_new';
}
