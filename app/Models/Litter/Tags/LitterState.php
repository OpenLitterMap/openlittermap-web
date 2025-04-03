<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LitterState extends Model
{
    use HasFactory;

    protected $table = 'litter_states';

    protected $guarded = [];
}
