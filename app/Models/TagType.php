<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TagType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'key';
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
