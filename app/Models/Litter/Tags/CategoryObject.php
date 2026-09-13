<?php

namespace App\Models\Litter\Tags;

use App\Traits\ManagesTaggables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
     * - Find the CLO ID from category_id and litter_object_id; return null if no CLO exists.
     * - Example: the same bottle object has different CLO IDs in alcohol and softdrinks.
     * - Use these two IDs from photo_tags; its stored category_litter_object_id is deprecated
     *   and can be null even when the category/object has a CLO.
     * - Cache the lookup for this process; clear it after CLO rows change.
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
     * - Clear the cached CLO IDs after CLO rows change.
     * - Example: create a CLO, clear this cache, then generate a photo summary using its ID.
     */
    public static function flushResolverCache(): void
    {
        self::$resolverCache = null;
    }

    /**
     * - Return CLOs with no merged_into_clo_id.
     * - A category move retires the old CLO but can leave the object active.
     * - Example: moving an object from other to dumping gives it a new active CLO.
     * - Keep the old CLO row so requests using its ID can find the replacement.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('merged_into_clo_id');
    }

    /**
     * - Hide historical CLOs from suggestions; existing observations still resolve them.
     * - Example: sanitary/gloves stays editable while medical/gloves is offered for new tags.
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_selectable', true);
    }

    /**
     * - A CLO the picker, search and suggestions may offer: not redirected and selectable.
     * - Every discovery path uses this one rule.
     */
    public function scopeOfferable(Builder $query): Builder
    {
        return $query->active()->selectable();
    }

    /**
     * - A historical CLO: declared only so saved observations stay editable, on a live object.
     * - Example: sanitary/gloves after medical/gloves became the current choice.
     */
    public function scopeHistorical(Builder $query): Builder
    {
        return $query->active()->where('is_selectable', false)
            ->whereHas('litterObject', fn (Builder $object) => $object->active());
    }

    public function isRetired(): bool
    {
        return $this->merged_into_clo_id !== null;
    }

    /**
     * - Return the final active CLO, or null if the replacement cannot be resolved.
     * - Example: CLO 10 → 20 → 30 returns CLO 30.
     *
     * @see resolveActiveMapping()
     */
    public function resolveActiveClo(): ?self
    {
        return $this->resolveActiveMapping()['clo'] ?? null;
    }

    /**
     * - Follow merged_into_clo_id to the final active CLO.
     * - If a retired object has no CLO redirect, follow its merged_into_id instead.
     * - Keep the latest merged_into_type_id set along the redirects.
     * - Example: CLO 10 → 20 → 30 returns CLO 30 and the type set on the last typed redirect.
     * - Return null for a missing replacement or a loop, e.g. CLO 10 → 20 → 10.
     * - Never create a CLO while resolving a tag.
     *
     * @return array{clo: self, type_id: int|null}|null
     */
    public function resolveActiveMapping(): ?array
    {
        $clo = $this;
        $typeId = null;
        $seen = [];

        while ($clo !== null) {
            if (isset($seen[$clo->id])) {
                return null;
            }
            $seen[$clo->id] = true;

            if ($clo->isRetired()) {
                $typeId = $clo->merged_into_type_id ?? $typeId;
                $clo = static::find($clo->merged_into_clo_id);
                continue;
            }

            $object = $clo->litterObject;
            if ($object === null) {
                return null;
            }
            if (! $object->isRetired()) {
                return ['clo' => $clo, 'type_id' => $typeId === null ? null : (int) $typeId];
            }

            // - This object is retired but its CLO has no redirect.
            // - Follow merged_into_id, then check the replacement CLO for further redirects.
            $active = $object->activeObject();
            $clo = $active === null ? null : static::where('category_id', $clo->category_id)
                ->where('litter_object_id', $active->id)->first();
        }

        return null;
    }

    /**
     * - Resolve the active CLO and type for photo tags and quick tags.
     * - If the request has no type, use the type recorded by the migration.
     * - Example: beer_can → can with type beer adds beer when the request omitted it.
     * - If the CLO changed, clear a type that is not allowed on the new CLO.
     * - If the CLO did not change, reject an invalid type with 422.
     * - Also return 422 when the retired CLO has no valid replacement.
     *
     * @return array{clo: self, type_id: int|null}
     */
    public function resolveForWrite(?int $typeId = null): array
    {
        $mapping = $this->resolveActiveMapping();
        if ($mapping === null) {
            throw ValidationException::withMessages([
                'tags' => ["Litter object '{$this->litterObject?->key}' is retired and can no longer be tagged."],
            ]);
        }

        $typeId ??= $mapping['type_id'];
        $destination = $mapping['clo'];
        if ($typeId !== null && ! DB::table('category_object_types')
            ->where('category_litter_object_id', $destination->id)
            ->where('litter_object_type_id', $typeId)->exists()) {
            if ((int) $destination->id === (int) $this->id) {
                throw ValidationException::withMessages([
                    'tags' => ["Type {$typeId} is not valid for CLO {$this->id}"],
                ]);
            }
            $typeId = null;
        }

        return ['clo' => $destination, 'type_id' => $typeId];
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
