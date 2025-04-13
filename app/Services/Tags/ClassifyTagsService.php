<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // Check if it's a deprecated tag
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

            // Run normal classification on the new key
            $result = $this->classifyNewKey($objectKey);

            // Do we need this?
            if (!empty($deprecatedTag['materials'])) {
                $result['materials'] = $deprecatedTag['materials'];
            }
            if (!empty($deprecatedTag['brands'])) {
                $result['brands'] = $deprecatedTag['brands'];
            }

            return $result;
        }

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

        Log::notice("Undefined tag classification: '{$key}'");
        return ['type' => 'undefined', 'key'  => $key];
    }

    public static function normalizeDeprecatedTag(string $key): ?array
    {
        return match ($key) {
            default => null,
            // Alcohol
            'beerBottle' => ['object' => 'beer_bottle', 'materials' => ['glass']],
            'beerCan' => ['object' => 'beer_can', 'materials' => ['aluminium']],
            'spiritBottle' => ['object' => 'spirits_bottle', 'materials' => ['glass']],
            'wineBottle' => ['object' => 'wine_bottle', 'materials' => ['glass']],
            'brokenGlass' => ['object' => 'brokenGlass', 'materials' => ['glass']],
            'bottleTops' => ['object' => 'bottleTop', 'materials' => ['metal']],
            'paperCardAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['cardboard', 'paper']],
            'plasticAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'pint' => ['object' => 'pint_glass', 'materials' => ['glass']],
            'six_pack_rings' => ['object' => 'sixPackRings', 'materials' => ['plastic']],
            'alcohol_plastic_cups' => ['object' => 'cup', 'materials' => ['plastic']],
            'alcoholOther' => ['object' => 'other'],

            // Coastal
            'degraded_plasticbottle' => ['object' => 'bottle', 'materials' => ['plastic'], 'states' => ['degraded']],
            'degraded_plasticbag' => ['object' => 'bag', 'materials' => ['plastic'], 'states' => ['degraded']],
            'coastal_other' => ['object' => 'other'],

            // Coffee
            'coffeeCups' => ['object' => 'cup', 'materials' => ['paper', 'plastic', 'foam', 'ceramic', 'metal']],
            'coffeeLids' => ['object' => 'lid', 'materials' => ['plastic', 'paper', 'bioplastic', 'plantFiber']],
            'coffeeOther' => ['object' => 'other'],

            // Food
            'sweetWrappers' => ['object' => 'wrapper', 'materials' => ['plastic']],
            'paperFoodPackaging' => ['object' => 'packaging', 'materials' => ['paper']],
            'plasticFoodPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'plasticCutlery' => ['object' => 'cutlery', 'materials' => ['plastic']],
            'crisp_small' => ['object' => 'crisps', 'materials' => ['foil'], 'sizes' => ['small', 'medium']],
            'crisp_large' => ['object' => 'crisps', 'materials' => ['foil'], 'sizes' => ['large']],
            'styrofoam_plate' => ['object' => 'plate', 'materials' => ['styrofoam']],
            'napkins' => ['object' => 'napkin', 'materials' => ['paper']],
            'sauce_packet' => ['object' => 'packet', 'materials' => ['plastic']],
            'glass_jar' => ['object' => 'jar', 'materials' => ['glass']],
            'glass_jar_lid' => ['object' => 'lid', 'materials' => ['glass']],
            'pizza_box' => ['object' => 'pizza_box', 'materials' => ['cardboard']],
            'aluminium_foil' => ['object' => 'tinfoil', 'materials' => ['aluminium']],
            'chewing_gum' => ['object' => 'gum', 'materials' => ['rubber']],
            'foodOther' => ['object' => 'other', 'materials' => []],

            // Industrial
            'industrial_plastic' => ['object' => 'plastic', 'materials' => ['plastic']],
            'bricks' => ['object' => 'bricks', 'materials' => ['clay']],
            'industrial_other' => ['object' => 'other'],

            // Other
            'random_litter' => ['object' => 'randomLitter'],
            'bags_litter' => ['object' => 'bagsLitter'],
            'overflowing_bins' => ['object' => 'overflowingBins'],
            'plastic_bags' => ['object' => 'plasticBags'],
            'traffic_cone' => ['object' => 'trafficCone'],
            'election_posters' => ['object' => 'posters'],
            'forsale_posters' => ['object' => 'posters'],
            'cable_tie' => ['object' => 'cableTie'],
            'washing_up' => ['object' => 'washingUp'],
            'life_buoy' => ['object' => 'life_buoy'],
            'clothing' => ['object' => 'clothing'],
            'balloons' => ['object' => 'balloons'],
            'umbrella' => ['object' => 'umbrella', 'materials' => ['plastic', 'metal', 'cloth']],
            'other' => ['object' => 'other'],

            // Sanitary
            'menstral' => ['object' => 'sanitaryPad', 'materials' => ['cotton', 'plastic']],
            'deodorant' => ['object' => 'deodorant_can', 'materials' => ['aluminium']],
            'ear_swabs' => ['object' => 'earSwabs', 'materials' => ['plastic', 'cotton']],
            'tooth_brush' => ['object' => 'toothbrush', 'materials' => ['plastic', 'nylon', 'bamboo', 'wood']],
            'hand_sanitiser' => ['object' => 'sanitiser', 'materials' => ['plastic']],
            'wetwipes' => ['object' => 'wipes', 'materials' => ['fabric', 'plastic', 'biodegradable']],
            'sanitaryOther' => ['object' => 'other'],

            // Smoking
            'cigaretteBox' => ['object' => 'cigarette_box', 'materials' => ['cardboard']],
            'skins' => ['object' => 'rollingPapers', 'materials' => ['paper']],
            'smoking_plastic' => ['object' => 'packaging', 'materials' => ['plastic']],
            'filterbox' => ['object' => 'filters', 'materials' => ['cardboard']],
            'vape_pen' => ['object' => 'vapePen', 'materials' => ['plastic', 'metal']],
            'vape_oil' => ['object' => 'vapeOil', 'materials' => ['plastic', 'glass']],
            'smokingOther' => ['object' => 'other'],

            // SoftDrinks
            'waterBottle' => ['object' => 'water_bottle', 'materials' => ['plastic']],
            'fizzyDrinkBottle' => ['object' => 'fizzy_bottle', 'materials' => ['plastic']],
            'bottleLid' => ['object' => 'lid', 'materials' => ['plastic']],
            'bottleLabel' => ['object' => 'label', 'materials' => ['plastic']],
            'tinCan' => ['object' => 'soda_can', 'materials' => ['aluminium']],
            'sportsDrink' => ['object' => 'sports_bottle', 'materials' => ['plastic']],
            'straws' => ['object' => 'straw', 'materials' => ['plastic']],
            'plastic_cups' => ['object' => 'cup', 'materials' => ['plastic']],
            'plastic_cup_tops' => ['object' => 'lid', 'materials' => ['plastic']],
            'milk_bottle' => ['object' => 'milk_bottle', 'materials' => ['plastic']],
            'milk_carton' => ['object' => 'milk_carton', 'materials' => ['plastic']],
            'paper_cups' => ['object' => 'cup', 'materials' => ['paper']],
            'pullring' => ['object' => 'pullRing', 'materials' => ['aluminium']],
            'juice_cartons' => ['object' => 'juice_carton', 'materials' => ['cardboard', 'foil', 'plastic']],
            'juice_bottles' => ['object' => 'juice_bottle', 'materials' => ['plastic']],
            'juice_packet' => ['object' => 'juice_pouch', 'materials' => ['plastic', 'foil']],
            'ice_tea_bottles' => ['object' => 'iceTea_bottle', 'materials' => ['glass']],
            'ice_tea_can' => ['object' => 'icedTea_can', 'materials' => ['aluminium']],
            'energy_can' => ['object' => 'energy_can', 'materials' => ['aluminium']],
            'strawpacket' => ['object' => 'straw_packaging', 'materials' => ['plastic']],
            'styro_cup' => ['object' => 'cup', 'materials' => ['styrofoam']],
            'broken_glass' => ['object' => 'brokenGlass', 'materials' => ['glass']],
            'softDrinkOther' => ['object' => 'other'],
        };
    }

    public function normalizeCustomTag(string $rawTag): array
    {
        $tag = trim($rawTag);
        $typeHint = null;
        $quantity = 1;

        // 1. Extract quantity if "=X" exists
        if (preg_match('/^(.*)=(\d+)$/', $tag, $matches)) {
            $tag = trim($matches[1]);
            $quantity = (int) $matches[2];
        }

        //  Extract prefix:postfix eg brand:pepsi
        if (str_contains($tag, ':')) {
            [$prefix, $value] = array_map('trim', explode(':', $tag, 2));
            $prefix = strtolower($prefix);
            $tag = $value;

            $typeHint = match ($prefix) {
                'brand', 'brands', 'bn' => 'Brand',
                'category', 'cat'       => 'Category',
                'object', 'objects'     => 'Object',
                'material', 'materials' => 'Material',
                default                 => null,
            };
        }

        $tagString = strtolower($tag);
        $tagType = $typeHint ?? $this->guessTagType($tagString);

        switch ($tagType) {
            case 'Category': $cacheRef = &$this->categories; break;
            case 'Brand':    $cacheRef = &$this->brands;     break;
            case 'Object':   $cacheRef = &$this->objects;    break;
            case 'Material': $cacheRef = &$this->materials;  break;
            default:         $cacheRef = &$this->customTags; break;
        }

        $model = match ($tagType) {
            'Category' => Category::class,
            'Brand' => BrandList::class,
            'Object' => LitterObject::class,
            'Material' => Materials::class,
            default => CustomTagNew::class,
        };

        return $this->createAndReturn(
            $tagString,
            $model,
            $cacheRef,
            strtolower($tagType),
            $quantity
        );
    }

    protected function guessTagType(string $key): string
    {
        if (isset($this->brands[$key])) return 'Brand';
        if (isset($this->objects[$key])) return 'Object';
        if (isset($this->materials[$key])) return 'Material';
        if (isset($this->categories[$key])) return 'Category';

        return 'Custom';
    }

    /**
     * Helper to fetch Category by normalized key.
     */
    public function getCategory(string $rawKey): ?Category
    {
        $key = $this->normalize($rawKey);

        return Category::where('key', $key)->first();
    }

    protected function createAndReturn(string $key, string $model, array &$cache, string $type, int $quantity): array
    {
        if (!array_key_exists($key, $cache)) {
            $created = $model::firstOrCreate(['key' => $key], ['crowdsourced' => true]);
            $id = $created->id;

            // Update the passed-in local cache
            $cache[$key] = $id;

            // Update the class-level cache
            match ($type) {
                'category' => $this->categories[$key] = $id,
                'brand'    => $this->brands[$key] = $id,
                'object'   => $this->objects[$key] = $id,
                'material' => $this->materials[$key] = $id,
                default    => $this->customTags[$key] = $id,
            };
        }

        return [
            'type' => $type,
            'key'  => $key,
            'id'   => $cache[$key],
            'quantity' => $quantity,
        ];
    }

    public function resolveBrandObjectLinks(int $photoId, array $group): array
    {
        $matched = [];

        $objects = $group['objects'] ?? [];
        $brands  = $group['brands']  ?? [];

        // If no objects or brands, nothing to do
        if (empty($objects) || empty($brands)) {
            return $matched;
        }

        // ─────────────────────────────────────────────────────────
        // CASE 1: Exactly 1 object AND 1 brand => auto-link
        // ─────────────────────────────────────────────────────────
        if (count($objects) === 1 && count($brands) === 1) {
            $matched[] = $this->linkBrandToObject(
                $group['category_id'],
                $objects[0],
                $brands[0]
            );
            return $matched;
        }

        // ─────────────────────────────────────────────────────────
        // CASE 2: If multiple objects AND multiple brands
        //   => look up existing brand↔object pivots, then log leftover
        // ─────────────────────────────────────────────────────────
        elseif (count($objects) > 1 && count($brands) > 1) {
            \Log::info("Photo #{$photoId} has multiple objects and multiple brands. Checking existing relationships...");

            // For convenience, store them in collections we can filter
            $unmatchedObjects = collect($objects);
            $unmatchedBrands  = collect($brands);

            // 1) For each brand, see if we have known pivots in category_object_taggables
            foreach ($brands as $brand) {
                // Get all catObj IDs for brand in the same category
                $catObjIds = \DB::table('category_object_taggables')
                    ->where('taggable_id', $brand['id'])
                    ->where('taggable_type', BrandList::class)
                    ->pluck('category_object_id');

                if ($catObjIds->isEmpty()) {
                    continue;
                }

                // Which objects do those catObj IDs represent?
                $objectIds = CategoryObject::whereIn('id', $catObjIds)
                    ->where('category_id', $group['category_id'])
                    ->pluck('litter_object_id')
                    ->all();

                // If any of our $unmatchedObjects share those objectIds, link them
                foreach ($unmatchedObjects as $objIndex => $objData) {
                    if (in_array($objData['id'], $objectIds, true)) {
                        // Found an existing pivot => link brand to object if not already
                        $matched[] = $this->linkBrandToObject(
                            $group['category_id'],
                            $objData,
                            $brand
                        );
                        // Remove from "unmatched" sets so we don't re-link them
                        $unmatchedObjects->forget($objIndex);
                        $unmatchedBrands = $unmatchedBrands->reject(
                            fn($b) => $b['id'] === $brand['id']
                        );
                        // We can break if we assume 1 brand→1 object
                        // or omit break if brand can match multiple objects
                        break;
                    }
                }
            }

            // 2) Log any leftover unmatched brands
            foreach ($unmatchedBrands as $brand) {
                \Log::warning("Unmatched brand '{$brand['key']}' in photo #{$photoId}");
            }
            foreach ($unmatchedObjects as $object) {
                \Log::warning("Unmatched object '{$object['key']}' in photo #{$photoId}");
            }

            return $matched;
        }

        // ─────────────────────────────────────────────────────────
        // CASE 3: If exactly one side is multiple, or some other pattern
        //   => fallback to your existing logic
        // ─────────────────────────────────────────────────────────
        else {
            \Log::info("Photo #{$photoId} has a partial multiple scenario; using normal pivot logic.");

            // We can reuse the 'multiple' pivot logic from above, or
            // just do the simpler approach. For example:
            return $this->attemptPartialMatches($photoId, $group);
        }
    }


    /**
     * Helper that:
     *   - Creates the pivot link between the brand & the object in the DB if not existing
     *   - Returns the brand-object array that your createPhotoTags() can later use
     */
    protected function linkBrandToObject(array $group, array $object, array $brand): array
    {
        // Create or find the category-object pivot
        $catObj = CategoryObject::firstOrCreate([
            'category_id' => $group['category_id'],
            'litter_object_id' => $object['id'],
        ]);

        // Attach the brand if not already
        $alreadyTagged = $catObj->taggables()
            ->where('taggable_id', $brand['id'])
            ->where('taggable_type', BrandList::class)
            ->exists();

        if (!$alreadyTagged) {
            $catObj->attachTaggables([$brand], BrandList::class);
            Log::info(
                "Auto-linked brand '{$brand['key']}' to object '{$object['key']}' in category_id={$group['category_id']}"
            );
        }

        // Return the linked pair for later usage in createPhotoTags()
        return [
            'object' => $object,
            'brand'  => $brand,
        ];
    }
}
