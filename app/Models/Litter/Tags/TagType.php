<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

// old 2-way pivot table
//    public function litterObjects (): BelongsToMany
//    {
//        return $this->belongsToMany(LitterObject::class, 'litter_object_tag_type');
//    }

    // New 3-way pivot table
    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(
            LitterObject::class,
            'category_litter_object_tag_type',
            'tag_type_id',
            'litter_object_id'
        )
        ->withPivot('category_id')
        ->withTimestamps();
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(
            Materials::class,
            'tag_type_material',
            'tag_type_id',
            'material_id'
        );
    }
}
