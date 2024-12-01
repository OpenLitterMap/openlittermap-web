<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materials extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $table = 'materials';

    public function photoTags (): BelongsToMany
    {
        return $this->belongsToMany(PhotoTag::class, 'material_photo_tag')->withTimestamps();
    }
}
