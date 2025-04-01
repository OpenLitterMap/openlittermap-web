<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;

class ClassifyTagsService
{
    protected array $categories = [];
    protected array $objects    = [];
    protected array $materials  = [];
    protected array $brands     = [];
    protected array $customTags = [];

    public function __construct()
    {
        $this->preloadCaches();
    }

    protected function preloadCaches(): void
    {
        $this->categories = Category::pluck('id', 'key')->all();
        $this->objects    = LitterObject::pluck('id', 'key')->all();
        $this->materials  = Materials::pluck('id', 'key')->all();
        $this->brands     = BrandList::pluck('id', 'key')->all();
        $this->customTags = CustomTagNew::pluck('id', 'key')->all();
    }

    /**
     * Normalize a tag string (lowercase, trim).
     */
    protected function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Main classify entry point. Handles old-tag => new-tag transformation if needed,
     * then runs the normal classification logic.
     */
    public function classify(string $rawTag): array
    {
        // 1) Normalize the raw tag
        $key = $this->normalize($rawTag);

        // 2) Check if it's an old tag with a known mapping to a new format
        $oldTagData = $this->handleOldTag($key);
        if ($oldTagData !== null) {
            // e.g. $oldTagData might look like:
            // [
            //   'newKey' => 'packaging',
            //   'extraMaterials' => ['paper', 'cardboard'],
            //   'extraBrands' => [],
            //   ...
            // ]
            // Replace $key with the new key
            $transformedKey = $oldTagData['object'] ?? $key;

            // 3) Run normal classification on the new key
            $result = $this->classifyNewKey($transformedKey);

            // 4) Merge in the extra data from oldTagData (e.g., extra materials)
            //    You can store them in extra fields on $result if needed:
            if (!empty($oldTagData['materials'])) {
                // For example, store them in $result['extra_material_keys']
                $result['materials'] = $oldTagData['materials'];
            }
            if (!empty($oldTagData['brands'])) {
                $result['brands'] = $oldTagData['brands'];
            }

            // You can add any additional merging logic here, e.g. if the old tag
            // directly maps to a brand, or if you need to set a different 'type' etc.

            return $result;
        }

        // If not an old tag, do the normal classification
        return $this->classifyNewKey($key);
    }

    /**
     * classifyNewKey() – the existing classification logic (brands, objects, materials, categories, custom).
     * We keep this separate so we can reuse it after we transform an old tag to a new key.
     */
    protected function classifyNewKey(string $key): array
    {
        if (isset($this->brands[$key])) {
            return [ 'type' => 'brand', 'id' => $this->brands[$key], 'key'  => $key ];
        }

        if (isset($this->objects[$key])) {
            return [ 'type' => 'object', 'id' => $this->objects[$key], 'key'  => $key ];
        }

        if (isset($this->materials[$key])) {
            return [ 'type' => 'material', 'id' => $this->materials[$key], 'key'  => $key ];
        }

        if (isset($this->categories[$key])) {
            return [ 'type' => 'category', 'id' => $this->categories[$key], 'key'  => $key ];
        }

        if (isset($this->customTags[$key])) {
            return [ 'type' => 'custom', 'id' => $this->customTags[$key], 'key'  => $key ];
        }

        return [ 'type' => 'undefined', 'key'  => $key ];
    }

    /**
     * Transform old keys to their new mappings.
     *
     * If $key is recognized as an old tag,
     * return an array describing how to transform it. Otherwise return null.
     */
    protected function handleOldTag(string $key): ?array
    {
        static $mapping = [
            'beerBottle' => [
                'object' => 'beer_bottle',
                'materials' => ['glass'],
            ],
            'paperCardAlcoholPackaging' => [
                'object' => 'packaging',
                'materials' => ['paper', 'cardboard'],
            ],
            'plasticAlcoholPackaging' => [
                'object' => 'packaging',
                'materials' => ['plastic'],
            ],
        ];

        // If it's in our map, return the transformation. Otherwise null.
        return $mapping[$key] ?? null;
    }

    /**
     * If you need direct DB lookups for older tags, you could do them here
     * or define more logic in handleOldTag(). E.g., if the old tag can map
     * to multiple new objects or produce different brand IDs. This remains a placeholder.
     */

    /**
     * Helper to fetch Category by normalized key.
     */
    public function getCategory(string $rawKey): ?Category
    {
        $key = $this->normalize($rawKey);
        return Category::where('key', $key)->first();
    }
}
