<?php

namespace App\Models\Litter\Tags;

use App\Models\Litter\Categories\Brand;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class CategoryObject extends Pivot
{
    public $incrementing = true;

    protected $table = 'category_litter_object';

    protected $guarded = [];

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

    public function materials(): MorphToMany
    {
        return $this->morphedByMany(
            Materials::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    public function brands(): MorphToMany
    {
        return $this->morphedByMany(
            Brand::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    public function attachTaggables(array $taggables, string $class): void
    {
        if (empty($taggables)) {
            return;
        }

        $rows = [];

        foreach ($taggables as $tag) {
            if (!isset($tag['id'])) {
                Log::warning("Skipping taggable with missing ID for class {$class}");
                continue;
            }

            $rows[] = [
                'category_litter_object_id' => $this->id,
                'taggable_type'             => $class,
                'taggable_id'               => $tag['id'],
                'quantity'                  => $tag['quantity'] ?? 1,
                'updated_at'                => now(),
                'created_at'                => now(),
            ];
        }

        if (!empty($rows)) {
            // Composite key: category_litter_object_id + taggable_type + taggable_id
            Taggable::upsert(
                $rows,
                ['category_litter_object_id', 'taggable_type', 'taggable_id'],
                ['quantity', 'updated_at']
            );
        }
    }
}
