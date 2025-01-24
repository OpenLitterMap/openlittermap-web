<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'category_litter_object_tag_type',  // triple pivot table
            'category_id',
            'litter_object_id'
        )
        // Because the pivot has 3 columns, we can also do ->withPivot('tag_type_id') if you want direct pivot info
        ->distinct();
    }
}
