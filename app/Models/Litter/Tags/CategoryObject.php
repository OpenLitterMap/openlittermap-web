<?php

namespace App\Models\Litter\Tags;

use App\Traits\ManagesTaggables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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

    /** @var array<string, int>|null category id:object id => CLO id */
    private static ?array $resolverCache = null;

    /**
     * The CLO for a category/object pairing, or null when the taxonomy does not sanction it.
     *
     * `photo_tags` records an observation as `category_id` + `litter_object_id`; the CLO is a
     * taxonomy row derived from that pairing, not an independent fact. A unique index on
     * (category_id, litter_object_id) makes the lookup a bijection. Prefer this over reading
     * `photo_tags.category_litter_object_id`, which is deprecated and null on rows written
     * before their pivot existed.
     */
    public static function resolveId(?int $categoryId, ?int $objectId): ?int
    {
        if ($categoryId === null || $objectId === null) {
            return null;
        }

        if (self::$resolverCache === null) {
            self::$resolverCache = static::query()
                ->get(['id', 'category_id', 'litter_object_id'])
                ->mapWithKeys(fn (self $clo) => [
                    $clo->category_id . ':' . $clo->litter_object_id => (int) $clo->id,
                ])
                ->all();
        }

        return self::$resolverCache[$categoryId . ':' . $objectId] ?? null;
    }

    /**
     * Drop the memoised map. Required after creating a pivot in a process that goes on to resolve
     * pairings — `MigrateTag` creates the survivor pivot and then regenerates summaries in the
     * same run.
     */
    public static function flushResolverCache(): void
    {
        self::$resolverCache = null;
    }

    /**
     * CLOs by id to decide retirement.
     * @param  array<int, int>  $cloIds
     * @return Collection<int, self>
     */
    public static function withRetirementState(array $cloIds): Collection
    {
        return static::query()
            ->whereIn('id', $cloIds)
            ->with('litterObject:id,key,retired_at,merged_into_id')
            ->get()
            ->keyBy('id');
    }

    /**
     * The CLO this write should land on. When the litter object is retired, returns
     * the existing CLO pairing the same category with the active object it merged into.
     * API writes never create taxonomy relationships: the approved migration owns survivor
     * pivot creation. No active object or no approved survivor CLO returns null so the caller
     * can 422.
     */
    public function resolveActiveClo(): ?self
    {
        $litterObject = $this->relationLoaded('litterObject')
            ? $this->litterObject
            : $this->litterObject()->first();

        if ($litterObject === null || ! $litterObject->isRetired()) {
            return $this;
        }

        $activeLitterObject = $litterObject->activeObject();

        if ($activeLitterObject === null) {
            return null;
        }

        if ($activeLitterObject->id === (int) $this->litter_object_id) {
            return $this;
        }

        return static::where('category_id', $this->category_id)
            ->where('litter_object_id', $activeLitterObject->id)
            ->first();
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
