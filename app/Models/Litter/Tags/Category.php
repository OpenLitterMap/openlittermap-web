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

    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(LitterObject::class, 'category_litter_object')
            ->using(CategoryLitterObject::class)
            ->withPivot('id')
            ->with('pivot.materials');
    }
}
