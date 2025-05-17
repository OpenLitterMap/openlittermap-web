<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Litter\Tags\Traits\InvalidatesTagKeyCache;

class Materials extends Model
{
    use HasFactory;
    use InvalidatesTagKeyCache;

    protected $guarded = [];

    public $table = 'materials';

    public $timestamps = false;
}
