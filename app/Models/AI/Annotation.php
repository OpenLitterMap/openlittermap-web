<?php

namespace App\Models\AI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annotation extends Model
{
    use HasFactory;

    protected $table = 'annotations';

    protected $fillable = [
        'photo_id',
        'category',
        'category_id',
        'tag',
        'tag_id',
        'brand',
        'brand_id',
        'supercategory_id',
        'segmentation',
        'bbox',
        'is_crowd',
        'area',
        'added_by',
        'verified_by'
    ];
}
