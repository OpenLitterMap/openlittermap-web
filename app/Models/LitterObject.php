<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_litter_object');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(
            Materials::class,
            'litter_object_material',
            'litter_object_id',
            'material_id'
        );
    }

    public function photoTags(): HasMany
    {
        return $this->hasMany(PhotoTag::class, 'object_id');
    }

    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(TagType::class, 'litter_object_tag_type');
    }
}
