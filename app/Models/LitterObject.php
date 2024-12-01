<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function categories() :BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_litter_object')->withTimestamps();
    }

    public function photoTags(): HasMany
    {
        return $this->hasMany(PhotoTag::class, 'object_id');
    }

    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(TagType::class, 'litter_object_tag_type')->withTimestamps();
    }
}
