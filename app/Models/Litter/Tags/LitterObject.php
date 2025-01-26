<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function photoTags(): HasMany
    {
        return $this->hasMany(PhotoTag::class, 'object_id');
    }

    public function materials(): MorphToMany
    {
        return $this->morphToMany(Materials::class, 'materialable');
    }

    // New 3-way pivot table
    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            TagType::class,
            'litter_models',
            'litter_object_id',
            'tag_type_id'
        )
        ->withPivot('category_id');
    }
}
