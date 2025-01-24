<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LitterObject extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function litterModels(): HasMany
    {
        return $this->hasMany(LitterModel::class);
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

    // old 2-way pivot table
//    public function tagTypes(): BelongsToMany
//    {
//        return $this->belongsToMany(TagType::class, 'litter_object_tag_type');
//    }

    // New 3-way pivot table
    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            TagType::class,
            'category_litter_object_tag_type',
            'litter_object_id',
            'tag_type_id'
        )
        ->withPivot('category_id');
    }
}
