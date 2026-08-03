<?php

namespace App\Models\Litter\Tags;

use App\Traits\ManagesTaggables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * IDs of the (category, object) pairs that may be offered in the tag picker.
     *
     * Selectability is defined by `TagsConfig` — the canonical taxonomy — NOT by whether a
     * pivot row happens to exist. Repairing orphaned tags creates pivots for pairs that are
     * valid historically but are not part of the current taxonomy (e.g. `other/plasticBags`,
     * and canonical-object pairs like `marine/bag`). Those must stay unselectable until a
     * taxonomy review promotes them, or users could tag new photos with retiring pairs.
     *
     * @return array<int, int>
     */
    public static function selectableIds(): array
    {
        $allowed = [];

        foreach (\App\Tags\TagsConfig::get() as $categoryKey => $objects) {
            foreach (array_keys($objects) as $objectKey) {
                $allowed["{$categoryKey}|{$objectKey}"] = true;
            }
        }

        return static::query()
            ->join('categories', 'categories.id', '=', 'category_litter_object.category_id')
            ->join('litter_objects', 'litter_objects.id', '=', 'category_litter_object.litter_object_id')
            ->select([
                'category_litter_object.id',
                'categories.key as category_key',
                'litter_objects.key as object_key',
            ])
            ->get()
            ->filter(fn ($row) => isset($allowed["{$row->category_key}|{$row->object_key}"]))
            ->pluck('id')
            ->all();
    }

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
     * Litter object types valid for this category-object combination
     */
    public function types(): BelongsToMany
    {
        return $this->belongsToMany(
            LitterObjectType::class,
            'category_object_types',
            'category_litter_object_id',
            'litter_object_type_id'
        );
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
