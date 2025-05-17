<?php

namespace App\Models\Litter\Tags;

use App\Models\Litter\Tags\Traits\InvalidatesTagKeyCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;
    use InvalidatesTagKeyCache;

    protected $primaryKey = 'id';

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
        return $this->belongsToMany(
            LitterObject::class,
            'category_litter_object',
            'category_id',
            'litter_object_id'
        )
        ->using(CategoryObject::class)
        ->withPivot('id')
        ->withTimestamps();
    }
}
