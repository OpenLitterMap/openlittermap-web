<?php

namespace App\Models\Litter\Tags;

use App\Traits\ManagesTaggables;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class CategoryObject extends Pivot
{
    use ManagesTaggables;

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $table = 'category_litter_object';

    protected $guarded = [];

    /**
     * Get the category that this pivot belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the litter object that this pivot belongs to
     */
    public function litterObject(): BelongsTo
    {
        return $this->belongsTo(LitterObject::class, 'litter_object_id');
    }

    /**
     * Materials that can be attached to this category-object combination
     */
    public function materials(): MorphToMany
    {
        return $this->morphToMany(
            Materials::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    /**
     * States that can be attached to this category-object combination
     */
    public function states(): MorphToMany
    {
        return $this->morphToMany(
            LitterState::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    /**
     * Brands that can be attached to this category-object combination
     */
    public function brands(): MorphToMany
    {
        return $this->morphToMany(
            BrandList::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    /**
     * Custom tags that can be attached to this category-object combination
     */
    public function customTags(): MorphToMany
    {
        return $this->morphToMany(
            CustomTagNew::class,
            'taggable',
            'taggables',
            'category_litter_object_id',
            'taggable_id'
        )->withPivot('quantity')->withTimestamps();
    }

    /**
     * Generic method to attach any taggable type
     *
     * @param array $taggables
     * @param string $class
     */
    public function attachTaggables(array $taggables, string $class): void
    {
        if (empty($taggables)) {
            return;
        }

        $rows = [];

        foreach ($taggables as $tag){
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
