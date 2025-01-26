<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
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

    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(
            LitterObject::class,
            'litter_models', // triple pivot table
            'category_id',
            'litter_object_id'
        )
        // Because the pivot has 3 columns, we can also do ->withPivot('tag_type_id') if you want direct pivot info
        ->distinct();
    }

    public function tagTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            TagType::class,
            'litter_models', // triple pivot table
            'category_id',
            'tag_type_id'
        )
        // Because the pivot has 3 columns, we can also do ->withPivot('litter_object_id') if you want direct pivot info
        ->distinct();
    }
}
