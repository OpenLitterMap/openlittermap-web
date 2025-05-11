<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materials extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $table = 'materials';

    public $timestamps = false;
}
