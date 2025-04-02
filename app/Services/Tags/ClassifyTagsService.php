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
    public function classify(string $tag): array
    {
        // 1) Check if it's a deprecated tag
        $deprecatedTag = $this->normalizeDeprecatedTag($tag);

        if ($deprecatedTag !== null) {
            // e.g. $deprecatedTag might look like:
            // [
            //   'object' => 'packaging',
            //   'materials' => ['paper', 'cardboard'],
            //   'brands' => [],
            //   ...
            // ]
            // Replace $key with the new key
            $objectKey = $deprecatedTag['object'] ?? $tag;

            // 3) Run normal classification on the new key
            $result = $this->classifyNewKey($objectKey);

            // 4) Merge in the extra data from oldTagData (e.g., extra materials)
            //    You can store them in extra fields on $result if needed:
            if (!empty($deprecatedTag['materials'])) {
                // For example, store them in $result['extra_material_keys']
                $result['materials'] = $deprecatedTag['materials'];
            }
            if (!empty($deprecatedTag['brands'])) {
                $result['brands'] = $deprecatedTag['brands'];
            }

            return $result;
        }

        // If not a deprecated tag, do the normal classification
        $key = $this->normalize($tag);

        return $this->classifyNewKey($key);
    }

    /**
     * classifyNewKey() – the existing classification logic (brands, objects, materials, categories, custom).
     * We keep this separate so we can reuse it after we transform an old tag to a new key.
     */
    public function classifyNewKey(string $key): array
    {
        if (isset($this->brands[$key])) {
            return ['type' => 'brand', 'id' => $this->brands[$key], 'key'  => $key];
        }

        if (isset($this->objects[$key])) {
            return ['type' => 'object', 'id' => $this->objects[$key], 'key'  => $key];
        }

        if (isset($this->materials[$key])) {
            return ['type' => 'material', 'id' => $this->materials[$key], 'key'  => $key];
        }

        if (isset($this->categories[$key])) {
            return ['type' => 'category', 'id' => $this->categories[$key], 'key'  => $key];
        }

        if (isset($this->customTags[$key])) {
            return ['type' => 'custom', 'id' => $this->customTags[$key], 'key'  => $key];
        }

        return ['type' => 'undefined', 'key'  => $key];
    }

    /**
     * @deprecated
     * Transform old keys to their new mappings.
     *
     * If $key is recognized as an old tag,
     * return an array describing how to transform it. Otherwise return null.
     */
    protected function handleDeprecatedTag(string $key): ?array
    {
        static $mapping = [

            // Alcohol
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

            // Smoking


        ];

        // If it's in our map, return the transformation. Otherwise null.
        return $mapping[$key] ?? null;
    }

    public static function normalizeDeprecatedTag(string $key): ?array
    {
        return match ($key) {

            // Alcohol
            'beerBottle' => ['object' => 'beer_bottle', 'materials' => ['glass']],
            'beerCan' => ['object' => 'beer_can', 'materials' => ['aluminium']],
            'spiritBottle' => ['object' => 'spirits_bottle', 'materials' => ['glass']],
            'wineBottle' => ['object' => 'wine_bottle', 'materials' => ['glass']],
            'brokenGlass' => ['object' => 'brokenGlass', 'materials' => ['glass']],
            'bottleTops' => ['object' => 'bottleTop', 'materials' => ['metal', 'plastic', 'cork']],
            'paperCardAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['cardboard', 'paper']],
            'plasticAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'pint' => ['object' => 'pint_glass', 'materials' => ['glass']],
            'six_pack_rings' => ['object' => 'sixPackRings', 'materials' => ['plastic']],
            'alcohol_plastic_cups' => ['object' => 'cup', 'materials' => ['plastic']],
            'alcoholOther' => ['object' => 'other', 'materials' => []],

            // Smoking
            'cigaretteBox' => ['object' => 'cigarette_box', 'materials' => ['cardboard']],
            'skins' => ['object' => 'rollingPapers', 'materials' => ['paper']],
            'smoking_plastic' => ['object' => 'packaging', 'materials' => ['plastic']],
            'filterbox' => ['object' => 'filters', 'materials' => ['plastic', 'biodegradable']],
            'vape_pen' => ['object' => 'vapePen', 'materials' => ['plastic', 'metal']],
            'vape_oil' => ['object' => 'vapeOil', 'materials' => ['plastic', 'glass']],
            'smokingOther' => ['object' => 'other', 'materials' => []],
            default => null
        };
    }

    /**
     * Helper to fetch Category by normalized key.
     */
    public function getCategory(string $rawKey): ?Category
    {
        $key = $this->normalize($rawKey);
        return Category::where('key', $key)->first();
    }
}
