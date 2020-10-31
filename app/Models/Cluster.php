<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'lat',
        'lon',
        'point_count',
        'point_count_abbreviated',
        'geohash',
        'zoom'
    ];
}
