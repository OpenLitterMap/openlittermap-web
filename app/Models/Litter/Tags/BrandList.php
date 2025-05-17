<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Litter\Tags\Traits\InvalidatesTagKeyCache;

class BrandList extends Model
{
    use HasFactory;
    use InvalidatesTagKeyCache;

    protected $guarded = [];

    public $table = 'brandslist';
}
