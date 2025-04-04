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

    protected $hidden = ['pivot'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function litterObjects(): BelongsToMany
    {
        return $this->belongsToMany(LitterObject::class, 'category_litter_object')
            ->using(CategoryObject::class)
            ->withPivot('id');
            // ->with('pivot.materials');
    }
}
