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
use Illuminate\Support\Facades\Schema;

class ClassifyTagsService
{
    protected array $categories = [];
    protected array $objects    = [];
    protected array $materials  = [];
    protected array $brands     = [];
    protected array $customTags = [];

    public function __construct()
    {
        if (Schema::hasTable('categories')) {
            $this->preloadCaches();
        }
    }

    protected function preloadCaches(): void
    {
        $this->categories = Category::pluck('id', 'key')->all();
        $this->objects    = LitterObject::pluck('id', 'key')->all();
        $this->materials  = Materials::pluck('id', 'key')->all();
        $this->brands     = BrandList::pluck('id', 'key')->all();
        $this->customTags = CustomTagNew::pluck('id', 'key')->all();
    }

    public function materialMap(): array
    {
        return $this->materials; // [key => id] pre‑hydrated in constructor
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
            'coffeeCups' => ['object' => 'cup', 'materials' => ['paper']],
            'coffeeLids' => ['object' => 'lid', 'materials' => ['plastic']],
            'coffeeOther' => ['object' => 'other'],

            // Dumping

            // Food
            'sweetWrappers' => ['object' => 'wrapper', 'materials' => ['plastic']],
            'paperFoodPackaging' => ['object' => 'packaging', 'materials' => ['paper']],
            'plasticFoodPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'plasticCutlery' => ['object' => 'cutlery', 'materials' => ['plastic']],
            'crisp_small' => ['object' => 'crisps', 'materials' => ['foil'], 'sizes' => ['small', 'medium']],
            'crisp_large' => ['object' => 'crisps', 'materials' => ['foil'], 'sizes' => ['large']],
            'styrofoam_plate' => ['object' => 'plate', 'materials' => ['styrofoam']],
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
            'bags_litter' => ['object' => 'bagsLitter'],
            'balloons' => ['object' => 'balloons'],
            'cable_tie' => ['object' => 'cableTie', 'materials' => ['plastic']],
            'clothing' => ['object' => 'clothing'],
            'election_posters' => ['object' => 'posters', 'materials' => ['plastic']],
            'forsale_posters' => ['object' => 'posters', 'materials' => ['plastic']],
            'life_buoy' => ['object' => 'life_buoy', 'materials' => ['plastic']],
            'metal' => ['object' => 'metal', 'materials' => ['metal']],
            'overflowing_bins' => ['object' => 'overflowingBins'],
            'plastic_bags' => ['object' => 'plasticBags', 'materials' => ['plastic']],
            'random_litter' => ['object' => 'randomLitter'],
            'traffic_cone' => ['object' => 'trafficCone', 'materials' => ['plastic']],
            'plastic' => ['object' => 'plastic', 'materials' => ['plastic']],
            'umbrella' => ['object' => 'umbrella', 'materials' => ['plastic', 'metal', 'cloth']],
            'washing_up' => ['object' => 'washingUp'],
            'other' => ['object' => 'other'],

            // Sanitary
            'menstral' => ['object' => 'menstrual', 'materials' => ['plastic']],
            'deodorant' => ['object' => 'deodorant_can', 'materials' => ['aluminium']],
            'ear_swabs' => ['object' => 'earSwabs', 'materials' => ['plastic', 'cotton']],
            'tooth_brush' => ['object' => 'toothbrush', 'materials' => ['plastic', 'nylon', 'bamboo', 'wood']],
            'hand_sanitiser' => ['object' => 'sanitiser', 'materials' => ['plastic']],
            'wetwipes' => ['object' => 'wipes', 'materials' => ['fabric', 'plastic', 'biodegradable']],
            'sanitaryOther' => ['object' => 'other'],

            // Smoking
            'cigaretteBox' => ['object' => 'cigarette_box', 'materials' => ['cardboard']],
            'lighters' => ['object' => 'lighters', 'materials' => ['plastic', 'metal']],
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

            // New Stationery category
            'stationary' => [ 'object' => 'other', 'category' => 'stationery' ],
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
        $objects = collect($group['objects']);   // [['id'=>7,'key'=>'beer_bottle'], …]
        $brands  = collect($group['brands']);    // [['id'=>9,'key'=>'heineken'],   …]

        // Bail out quickly if nothing to match
        if ($objects->isEmpty() || $brands->isEmpty()) {
            return [];
        }

        // ───────────────────────────────────────────────
        // CASE 1 : exactly 1 object + 1 brand
        // ───────────────────────────────────────────────
        if ($objects->count() === 1 && $brands->count() === 1) {
            return [
                $this->createPivotIfMissing(
                    $group['category_id'],
                    $objects->first(),
                    $brands->first()
                ),
            ];
        }

        // ───────────────────────────────────────────────
        // CASE 2 : many objects + many brands
        // ───────────────────────────────────────────────
        // 1) Load all pivots for *these* objects in *this* category at once
        $catObjIds = CategoryObject::where('category_id', $group['category_id'])
            ->whereIn('litter_object_id', $objects->pluck('id'))
            ->pluck('id', 'litter_object_id');                      // [objectId => catObjId]

        $existing = DB::table('taggables')
            ->whereIn('category_litter_object_id', $catObjIds->values())
            ->where('taggable_type', BrandList::class)
            ->whereIn('taggable_id', $brands->pluck('id'))
            ->get(['category_litter_object_id', 'taggable_id'])     // rows we already have
            ->groupBy('category_litter_object_id');

        $matched = [];

        // 2) iterate once through objects; for each, see if *its* catObj has one of the brands
        foreach ($objects as $object)
        {
            $catObjId = $catObjIds[$object['id']] ?? null;

            if (!$catObjId) {
                continue;
            }

            $brandIdsForObj = $existing[$catObjId] ?? collect();

            foreach ($brandIdsForObj as $row) {
                $brand = $brands->firstWhere('id', $row->taggable_id);
                if ($brand) {
                    $matched[] = ['object' => $object, 'brand' => $brand];
                }
            }
        }

        // 3) log every (object,brand) pair that did NOT have a pivot
        $matchedHashes = collect($matched)->map(
            fn ($p) => $p['object']['id'].'-'.$p['brand']['id']
        )->all();

        foreach ($objects as $o) {
            foreach ($brands as $b) {
                $hash = $o['id'].'-'.$b['id'];
                if (!in_array($hash, $matchedHashes, true)) {
                    Log::warning(
                        "No pivot for photo #{$photoId}: object='{$o['key']}', brand='{$b['key']}'"
                    );
                }
            }
        }

        return $matched;
    }

    private function createPivotIfMissing(int $categoryId, array $object, array $brand): array
    {
        $catObj = CategoryObject::firstOrCreate([
            'category_id'      => $categoryId,
            'litter_object_id' => $object['id'],
        ]);

        $catObj->attachTaggables([$brand], BrandList::class);

        return ['object' => $object, 'brand' => $brand];
    }
}
