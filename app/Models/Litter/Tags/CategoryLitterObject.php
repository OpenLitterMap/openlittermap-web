<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryLitterObject extends Pivot
{
    protected $table = 'category_litter_object';

    protected $guarded = [];

    /**
     * Get the category that this pivot belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the litter object that this pivot belongs to.
     */
    public function litterObject(): BelongsTo
    {
        return $this->belongsTo(LitterObject::class, 'litter_object_id');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(
            Materials::class,
            'category_litter_object_material',
            'category_litter_object_id',
            'material_id'
        );
    }
}
