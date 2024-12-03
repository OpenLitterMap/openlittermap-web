<?php

namespace App\Models;

use App\Models\Litter\Categories\Material;
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

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'litter_object_material')->withTimestamps();
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
