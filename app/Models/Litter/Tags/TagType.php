<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TagType extends Model
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

    // New 3-way pivot table
    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(
            LitterObject::class,
            'litter_models',
            'tag_type_id',
            'litter_object_id'
        )
        ->withPivot('category_id');
    }

    public function materials(): MorphToMany
    {
        return $this->morphToMany(Materials::class, 'materialable');
    }
}
