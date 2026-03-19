<?php

namespace App\Models\Litter\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Taggable extends Model
{
    protected $table = 'taggables';

    protected $fillable = [
        'category_litter_object_id',
        'taggable_type',
        'taggable_id',
        'quantity',
    ];

    // Brands
    // Materials
    // State
    // Sizes
    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function categoryLitterObject(): BelongsTo
    {
        return $this->belongsTo(CategoryObject::class);
    }
}
