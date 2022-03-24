<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminVerificationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'added_tags' => 'array',
        'removed_tags' => 'array'
    ];
}
