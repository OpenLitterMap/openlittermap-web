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

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_litter_object')->using(CategoryLitterObject::class);
    }

    /**
     * (Optional) Global materials relationship.
     * This uses a polymorphic many-to-many relation if other models also need materials.
     */
    public function materials(): MorphToMany
    {
        return $this->morphToMany(Materials::class, 'materialable', 'materialables');
    }
}
