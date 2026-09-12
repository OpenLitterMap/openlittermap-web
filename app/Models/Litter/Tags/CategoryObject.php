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
     * Category-object pairings available for new tags.
     *
     * Setting `merged_into_clo_id` retires a pairing and records its replacement pairing ID.
     * Keep the old row so clients submitting its ID can be redirected to the replacement.
     * The object itself can remain active when only its category changes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('merged_into_clo_id');
    }

    /**
     * Whether this pairing has been retired into another, regardless of the object's state.
     */
    public function isRetired(): bool
    {
        return $this->merged_into_clo_id !== null;
    }

    /**
     * The CLO this write should land on.
     *
     * @see resolveActiveMapping()
     */
    public function resolveActiveClo(): ?self
    {
        return $this->resolveActiveMapping()['clo'] ?? null;
    }

    /**
     * Find the final replacement pairing and the subtype recorded along the way.
     *
     * - Follow pairing redirects, including category moves.
     * - Fall back to object replacements for older retirement records.
     * - Carry forward the latest subtype specified by a mapping.
     * - Return null for missing destinations or cycles; never create a pairing.
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

            // Older retirements may have only an object replacement.
            // Its pairing can also have moved, so continue through the same loop.
            $active = $object->activeObject();
            $clo = $active === null ? null : static::where('category_id', $clo->category_id)
                ->where('litter_object_id', $active->id)->first();
        }

        return null;
    }

    /**
     * Resolve the pairing and subtype for a photo tag or saved preset.
     *
     * - Follow every recorded replacement.
     * - Use the migration's subtype when the client omitted one.
     * - Drop a stale subtype only when the pairing changed.
     * - Reject invalid types on an unchanged pairing and unresolved retirements.
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
