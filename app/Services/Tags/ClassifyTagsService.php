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
        $deprecatedMapping = self::normalizeDeprecatedTag($tag);

        if ($deprecatedMapping !== null) {
            $objectKey = $deprecatedMapping['object'] ?? $tag;

            // Run normal classification on the new key
            $result = $this->classifyNewKey($objectKey);

            if (!empty($deprecatedMapping['materials'])) {
                $result['materials'] = $deprecatedMapping['materials'];
            }
            if (!empty($deprecatedMapping['brands'])) {
                $result['brands'] = $deprecatedMapping['brands'];
            }
            if (!empty($deprecatedMapping['states'])) {
                $result['states'] = $deprecatedMapping['states'];
            }
            if (!empty($deprecatedMapping['sizes'])) {
                $result['sizes'] = $deprecatedMapping['sizes'];
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

        /** -----------------------------------------------------------------
         *  Fallback: treat unknown slug as a new *object* (or custom tag)
         * ------------------------------------------------------------------
         * 1) create a `litter_objects` row if it does not exist
         * 2) put the id into the in-memory cache so the next lookup is fast
         */
        $created = LitterObject::firstOrCreate(
            ['key' => $key],
            ['crowdsourced' => true]        // or other default columns
        );

        $this->objects[$key] = $created->id;  // update cache for current request

        Log::info("Autocreated new object slug '{$key}' (#{$created->id})");

        return ['type' => 'object', 'id' => $created->id, 'key' => $key];
    }

    // We only need to map the keys that have changed.
    public static function normalizeDeprecatedTag(string $key): ?array
    {
        return match ($key) {
            // Only map keys that have CHANGED from old to new
            // If the key exists in TagsConfig with the same name, don't map it

            // Alcohol - most stay the same except:
            'beerBottle' => ['object' => 'beer_bottle', 'materials' => ['glass']],
            'beerCan' => ['object' => 'beer_can', 'materials' => ['aluminium']],
            'spiritBottle' => ['object' => 'spirits_bottle', 'materials' => ['glass']],
            'wineBottle' => ['object' => 'wine_bottle', 'materials' => ['glass']],
            'paperCardAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['cardboard', 'paper']],
            'plasticAlcoholPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'pint' => ['object' => 'pint_glass', 'materials' => ['glass']],
            'alcohol_plastic_cups' => ['object' => 'cup', 'materials' => ['plastic']],
            'alcoholOther' => ['object' => 'other'],

            // Coffee
            'coffeeCups' => ['object' => 'cup', 'materials' => ['paper']],
            'coffeeLids' => ['object' => 'lid', 'materials' => ['plastic']],
            'coffeeOther' => ['object' => 'other'],

            // Food
            'sweetWrappers' => ['object' => 'wrapper', 'materials' => ['plastic']],
            'paperFoodPackaging' => ['object' => 'packaging', 'materials' => ['paper']],
            'plasticFoodPackaging' => ['object' => 'packaging', 'materials' => ['plastic']],
            'plasticCutlery' => ['object' => 'cutlery', 'materials' => ['plastic']],
            'styrofoam_plate' => ['object' => 'plate', 'materials' => ['styrofoam']],
            'sauce_packet' => ['object' => 'packet', 'materials' => ['plastic']],
            'glass_jar_lid' => ['object' => 'lid', 'materials' => ['glass']],
            'aluminium_foil' => ['object' => 'tinfoil', 'materials' => ['aluminium']],
            'chewing_gum' => ['object' => 'gum', 'materials' => ['rubber']],
            'foodOther' => ['object' => 'other'],

            // Smoking
            'butts' => ['object' => 'butts', 'materials' => ['plastic', 'paper']],
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

            // Sanitary
            'menstral' => ['object' => 'menstrual', 'materials' => ['plastic']],
            'deodorant' => ['object' => 'deodorant_can', 'materials' => ['aluminium']],
            'ear_swabs' => ['object' => 'earSwabs', 'materials' => ['plastic', 'cotton']],
            'tooth_brush' => ['object' => 'toothbrush', 'materials' => ['plastic']],
            'tooth_pick' => ['object' => 'toothpick', 'materials' => ['wood']],
            'hand_sanitiser' => ['object' => 'sanitiser', 'materials' => ['plastic']],
            'sanitaryOther' => ['object' => 'other'],

            // Coastal
            'degraded_plasticbottle' => ['object' => 'bottle', 'materials' => ['plastic'], 'states' => ['degraded']],
            'degraded_plasticbag' => ['object' => 'bag', 'materials' => ['plastic'], 'states' => ['degraded']],
            'degraded_straws' => ['object' => 'straws', 'materials' => ['plastic'], 'states' => ['degraded']],
            'degraded_lighters' => ['object' => 'lighters', 'materials' => ['plastic'], 'states' => ['degraded']],
            'rope_small' => ['object' => 'rope', 'materials' => ['rope', 'plastic'], 'sizes' => ['small']],
            'rope_medium' => ['object' => 'rope', 'materials' => ['rope', 'plastic'], 'sizes' => ['medium']],
            'rope_large' => ['object' => 'rope', 'materials' => ['rope', 'plastic'], 'sizes' => ['large']],
            'fishing_gear_nets' => ['object' => 'fishing_nets', 'materials' => ['rope', 'plastic']],
            'ghost_nets' => ['object' => 'fishing_nets', 'materials' => ['rope', 'plastic']],
            'styro_small' => ['object' => 'styrofoam', 'materials' => ['styrofoam'], 'sizes' => ['small']],
            'styro_medium' => ['object' => 'styrofoam', 'materials' => ['styrofoam'], 'sizes' => ['medium']],
            'styro_large' => ['object' => 'styrofoam', 'materials' => ['styrofoam'], 'sizes' => ['large']],
            'coastal_other' => ['object' => 'other'],

            // Industrial
            'industrial_plastic' => ['object' => 'plastic', 'materials' => ['plastic']],
            'industrial_other' => ['object' => 'other'],

            // Other
            'bags_litter' => ['object' => 'bagsLitter'],
            'overflowing_bins' => ['object' => 'overflowingBins'],
            'plastic_bags' => ['object' => 'plasticBags'],
            'random_litter' => ['object' => 'randomLitter'],
            'traffic_cone' => ['object' => 'trafficCone'],
            'election_posters' => ['object' => 'posters'],
            'forsale_posters' => ['object' => 'posters'],
            'cable_tie' => ['object' => 'cableTie'],
            'washing_up' => ['object' => 'washingUp'],
            'stationary' => ['object' => 'other', 'category' => 'stationery'],

            // Dogshit/Pets (category rename)
            'poo' => ['object' => 'dogshit', 'category' => 'pets'],
            'poo_in_bag' => ['object' => 'dogshit_in_bag', 'category' => 'pets'],
            'pooinbag' => ['object' => 'dogshit_in_bag', 'category' => 'pets'],

            // Dumping (structural change)
            'small' => ['object' => 'dumping', 'sizes' => ['small']],
            'medium' => ['object' => 'dumping', 'sizes' => ['medium']],
            'large' => ['object' => 'dumping', 'sizes' => ['large']],

            // Return null for everything else - use key as-is
            default => null,
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
}
