<?php

namespace Database\Seeders\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Categories\Material;
use App\Models\Litter\Tags\CategoryLitterObject;
use Illuminate\Database\Seeder;

class GenerateTagsSeeder extends Seeder
{
    public function run (): void
    {
        $categories = Photo::categories();

        foreach ($categories as $category) {
            Category::firstOrCreate([
                'key' => $category,
            ]);
        }

        // old model
        $materials = Material::types();

        // newer model
        foreach ($materials as $material) {
            Materials::firstOrCreate([
                'key' => $material,
            ]);
        }

        // Category => LitterObject[] => [ MorphMany Material[] ]
        // LitterModel => Materials[]

        $categoryTags = [

            // Category
            'alcohol' => [
                // Object => Material
                'beer_bottle' => ['material:glass'],
                'cider_bottle' => ['material:glass', 'material:plastic'],
                'spirits_bottle' => ['material:glass'],
                'wine_bottle' => ['material:glass'],

                'beer_can' => ['material:aluminium'],
                'spirits_can' => ['material:aluminium'],
                'cider_can' => ['material:aluminium'],

                'wine_glass' => ['material:glass'],
                'pint_glass' => ['material:glass'],
                'shot_glass' => ['material:glass'],

                'bottleTop' => ['material:metal', 'material:plastic', 'material:cork'],
                'brokenGlass' => ['material:glass'],

                'cup' => ['material:plastic'],
                'packaging' => ['material:cardboard', 'material:paper', 'material:plastic'],
                'pull_ring' => ['material:aluminium'],
                'straw' => ['material:plastic', 'material:paper', 'material:metal'],
                'sixPackRings' => ['material:plastic'],
                'other'
            ],

            'automobile' => [
                'car_part' => ['material:metal', 'material:plastic', 'material:rubber', 'material:glass'],
                'battery' => ['material:metal', 'material:plastic'],
                'alloy' => ['material:metal'],
                'bumper' => ['material:plastic', 'material:metal'],
                'exhaust' => ['material:metal'],
                'engine' => ['material:metal'],
                'mirror' => ['material:glass', 'material:plastic'],
                'light' => ['material:glass', 'material:plastic'],
                'license_plate' => ['material:metal', 'material:plastic'],
                'oil_can' => ['material:metal', 'material:plastic'],
                'tyre' => ['material:rubber'],
                'wheel' => ['material:metal'],
                'other'
            ],

            'coastal' => [
                'microplastics' => ['material:plastic'],
                'mediumplastics' => ['material:plastic'],
                'macroplastics' => ['material:plastic'],
                'rope_small' => ['material:rope', 'material:plastic'],
                'rope_medium' => ['material:rope', 'material:plastic'],
                'rope_large' => ['material:rope', 'material:plastic'],
                'fishing_gear_nets' => ['material:rope', 'material:plastic'],
                'ghost_nets' => ['material:rope', 'material:plastic'],
                'buoys' => ['material:plastic'],
                'degraded_bottle' => ['material:plastic'],
                'degraded_bag' => ['material:plastic'],
                'degraded_straws' => ['material:plastic'],
                'degraded_lighters' => ['material:plastic'],
                'balloons' => ['material:plastic', 'material:latex'],
                'lego' => ['material:plastic'],
                'shotgun_cartridges' => ['material:metal', 'material:plastic'],
                'styro_small' => ['material:styrofoam'],
                'styro_medium' => ['material:styrofoam'],
                'styro_large' => ['material:styrofoam'],
                'other',
            ],

            'coffee' => [
                'cup' => ['material:paper', 'material:plastic', 'material:foam', 'material:ceramic', 'material:metal'],
                'lid' => ['material:plastic', 'material:paper', 'material:bioplastic', 'material:plantFiber'],
                'stirrer' => ['material:wood', 'material:plastic','material:metal','material:bamboo'],
                'packaging' => ['material:plastic', 'material:foil', 'material:paper'],
                'pod' => ['material:plastic', 'material:aluminium'],
                'sleeves' => ['material:cardboard', 'material:silicone'],
                'other'
            ],

            'electronics' => [
                'battery' =>  ['material:metal'],
                'cable' => ['material:plastic', 'material:copper'],
                'mobilePhone' => ['material:metal', 'material:plastic', 'material:glass'],
                'laptop' => ['material:metal', 'material:plastic', 'material:glass'],
                'tablet' => ['material:metal', 'material:plastic', 'material:glass'],
                'charger' => ['material:plastic', 'material:metal'],
                'headphones' => ['material:plastic', 'material:metal'],
                'other'
            ],

            'dumping' => [
                'small',
                'medium',
                'large'
            ],

            'food' => [
                'wrapper' => [
                    'material:plastic',
                    'material:paper',
                    'material:foil',
                    'material:bioplastic',
                ],
                'packet' => [
                    'material:plastic',
                    'material:foil',
                    'material:paper',
                ],
                'packaging' => [
                    'material:plastic',
                    'material:paper',
                    'material:foam',
                    'material:cardboard',
                    'material:bioplastic',
                ],
                'cutlery' => [
                    'material:plastic',
                    'material:wood',
                    'material:bioplastic',
                    'material:bamboo',
                    'material:metal',
                ],
                'crisps' => ['material:foil'],
                'plate' => [
                    'material:plastic',
                    'material:paper',
                    'material:foam',
                    'material:ceramic',
                    'material:metal',
                    'material:glass',
                    'material:bioplastic',
                ],
                'napkin' => ['material:paper', 'material:cloth', 'material:biodegradable'],
                'jar' => ['material:glass', 'material:plastic', 'material:metal'],
                'lid' => ['material:ceramic', 'material:metal', 'material:plastic', 'material:glass'],
                'tinfoil' => ['material:aluminium'],
                'box' => ['material:cardboard', 'material:plastic', 'material:wood', 'material:metal'],
                'pizza_box' => ['material:cardboard'],
                'gum' => ['material:rubber',],
                'bag' => ['material:plastic', 'material:paper', 'material:cloth', 'material:bioplastic'],
                'can' => ['material:aluminium', 'material:steel'],
                'other',
            ],

            'industrial' => [
                'oil' => ['material:oil'],
                'oilDrum' => ['material:metal', 'material:plastic'],
                'chemical' => ['material:chemical'],
                'plastic' => ['material:plastic'],
                'construction' => [
                    'material:clay',
                    'material:concrete',
                    'material:paper',
                    'material:plastic',
                    'material:metal',
                    'material:fiberglass',
                    'material:foam',
                    'material:asphalt',
                    'material:ceramic',
                    'material:stone'
                ],
                'tape' => ['material:plastic', 'material:adhesive'],
                'pallet' => ['material:wood', 'material:plastic'],
                'wire' => ['material:copper', 'material:plastic','material:steel'],
                'pipe' => ['material:metal', 'material:plastic', 'material:concrete'],
                'container' => ['material:metal', 'material:plastic'],
                'other',
            ],

            'other' => [
                'clothing',
                'randomLitter',
                'bagsLitter',
                'overflowingBins',
                'plastic',
                'trafficCone',
                'metal',
                'plasticBags',
                'posters',
                'cableTie',
                'washingUp',
                'balloons',
                'life_buoy', // coastal? -> also rivers
                'furniture',
                'mattress',
                'appliance',
                'paintCan',
                'other',
                'graffiti',
                'umbrella' => ['material:plastic', 'material:metal', 'material:cloth'],
            ],

            'pets' => [
                'dogshit',
                'dogshit_in_bag' => ['material:plastic'],
            ],

            'sanitary' => [
                'gloves' => [
                    'material:latex',
                    'material:vinyl',
                    'material:rubber',
                    'material:plastic',
                    'material:cloth',
                    'material:cotton'
                ],
                'facemask' => [
                    'material:cotton',
                    'material:polyester',
                    'material:paper',
                ],
                'condoms' => ['material:latex',],
                'condom_wrapper' => ['material:plastic', 'material:foil'],
                'nappies' => ['material:plastic', 'material:cloth', 'material:biodegradable'],
                'sanitaryPad' => ['material:cotton', 'material:plastic'],
                'tampon' => ['material:plastic'],
                'deodorant_can' => ['material:aluminium'],
                'earSwabs' => ['material:plastic', 'material:cotton'],
                'toothbrush' => ['material:plastic', 'material:nylon', 'material:bamboo', 'material:wood'],
                'toothpasteTube' => ['material:plastic', 'material:aluminium'],
                'toothpasteBox' => ['material:cardboard'],
                'dentalFloss' => ['material:nylon', 'material:plastic'],
                'mouthwashBottle' => ['material:plastic', 'material:glass'],
                'wipes' => ['material:fabric', 'material:plastic', 'material:biodegradable'],
                'sanitiser' => ['material:plastic'],
                'syringe' => ['material:plastic', 'material:metal'],
                'bandage' => ['material:cotton', 'material:elastic'],
                'plaster' => ['material:plastic', 'material:adhesive'],
                'medicineBottle' => ['material:plastic', 'material:glass'],
                'pillPack' => ['material:plastic', 'material:aluminium'],
                'other'
            ],

            'smoking' => [
                'butts' => ['material:plastic'], // suggestedTags: , 'material:paper', 'material:biodegradable'
                'lighters' => ['material:plastic', 'material:metal'],
                'cigarette_box' => ['material:cardboard'],
                'match_box' => ['material:cardboard'],
                'tobaccoPouch' => ['material:plastic'],
                'rollingPapers' => ['material:paper'],
                'packaging' => ['material:cellophane', 'material:foil'],
                'filters' => ['material:plastic', 'material:biodegradable'],
                'vapePen' => ['material:plastic', 'material:metal'],
                'vapeOil' => ['material:plastic', 'material:glass'],
                'pipe' => ['material:glass', 'material:metal', 'material:ceramic'],
                'bong' => ['material:glass', 'material:metal', 'material:ceramic'],
                'grinder' => ['material:metal', 'material:plastic'],
                'ashtray' => ['material:glass', 'material:ceramic', 'material:metal'],
                'other'
            ],

            'softdrinks' => [
                'water_bottle' => ['material:plastic', 'material:glass'],
                'fizzy_bottle' => ['material:plastic', 'material:glass'],
                'juice_bottle' => ['material:plastic', 'material:glass'],
                'energy_bottle' => ['material:plastic', 'material:glass'],
                'sports_bottle' => ['material:plastic', 'material:glass'],
                'iceTea_bottle' => ['material:plastic', 'material:glass'],
                'milk_bottle' => ['material:plastic', 'material:glass'],
                'smoothie_bottle' => ['material:plastic', 'material:glass'],

                'soda_can' => ['material:aluminium'],
                'energy_can' => ['material:aluminium'],
                'juice_can' => ['material:aluminium'],
                'icedTea_can' => ['material:aluminium'],
                'sparklingWater_can' => ['material:aluminium'],

                'juice_carton' => ['material:cardboard', 'material:foil', 'material:plastic'],
                'milk_carton' => ['material:cardboard', 'material:foil', 'material:plastic'],
                'icedTea_carton' => ['material:cardboard', 'material:foil', 'material:plastic'],
                'plantMilk_carton' => ['material:cardboard', 'material:foil', 'material:plastic'],

                'cup' => ['material:plastic', 'material:paper', 'material:foam'],

                'drinkingGlass' => ['material:glass'],
                'brokenGlass' => ['material:glass'],
                'lid' => ['material:plastic'],
                'label' => ['material:paper', 'material:plastic'],
                'pullRing' => ['material:aluminium'],

                'packaging' => ['material:cardboard', 'material:plastic', 'material:foil'],
                'straw' => ['material:plastic', 'material:paper', 'material:metal', 'material:bamboo'],
                'straw_packaging' => ['material:paper', 'material:plastic'],
                'juice_pouch' => ['material:plastic', 'material:foil'],
                'other'
            ],

            'stationery' => [
                'book' => ['material:paper'],
                'pen' => ['material:plastic', 'material:metal'],
                'pencil' => ['material:wood', 'material:graphite'],
                'magazine' => ['material:paper', 'material:plastic'],
                'marker' => ['material:plastic'],
                'notebook' => ['material:paper'],
                'stapler' => ['material:metal', 'material:plastic'],
                'paperClip' => ['material:metal'],
                'rubberBand' => ['material:rubber'],
            ],
        ];

        foreach ($categoryTags as $categoryKey => $objectsAndTags)
        {
            $category = Category::firstOrCreate(['key' => $categoryKey]);

            $this->processLitterTags($category, null, $objectsAndTags);
        }
    }

    /**
     * Recursively process the given tags for a Category.
     *
     * @param  Category           $category
     * @param  LitterObject|null  $parent  Null if top-level
     * @param  mixed              $data    (nested array or a single string)
     */
    protected function processLitterTags(Category $category, ?LitterObject $parent, mixed $data): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_int($key)) {
                    // When the key is numeric, the value is a string tag.
                    $this->handleStringItem($category, $parent, $value);
                } else {
                    // When the key is a string (like "beer_bottle" or "battery"),
                    // treat it as a new LitterObject with an array of tags.
                    $this->handleArrayItem($category, $parent, $key, $value);
                }
            }
        } else {
            // If $data is a single string.
            $this->handleStringItem($category, $parent, $data);
        }
    }

    /**
     * Handle a single string item.
     *
     * If the item starts with "material:" and a parent exists, then attach
     * that material to the litter object (both globally and contextually).
     * Otherwise, treat the string as a litter object key.
     *
     * @param  Category           $category
     * @param  LitterObject|null  $parent
     * @param  string             $item
     */
    protected function handleStringItem(Category $category, ?LitterObject $parent, string $item): void
    {
        if (str_starts_with($item, 'material:')) {
            // Process a material item.
            $materialKey = substr($item, strlen('material:'));
            $material = Materials::firstOrCreate(['key' => $materialKey]);

            if ($parent) {
                // Attach the material in the context of the (category, litter object) pivot.
                $this->attachMaterialToPivot($category, $parent, $material);
            }
        } else {
            // Otherwise, treat the string as a LitterObject key (e.g., "other").
            $litterObject = LitterObject::firstOrCreate(['key' => $item]);

            // Create (or update) the pivot record for the category and litter object.
            CategoryLitterObject::firstOrCreate([
                'category_id'      => $category->id,
                'litter_object_id' => $litterObject->id,
            ]);
        }
    }

    /**
     * Handle an array item where the key is a litter object and the value is its tags.
     *
     * @param  Category           $category
     * @param  LitterObject|null  $parent
     * @param  string             $key    Litter object key
     * @param  array              $value  Array of tags (could include material tags)
     */
    protected function handleArrayItem(Category $category, ?LitterObject $parent, string $key, array $value): void
    {
        // Treat $key as a litter object.
        $litterObject = LitterObject::firstOrCreate(['key' => $key]);

        // Create (or update) the pivot record between the category and this litter object.
        CategoryLitterObject::firstOrCreate([
            'category_id'      => $category->id,
            'litter_object_id' => $litterObject->id,
        ]);

        // Recursively process the nested tags, using this litter object as the parent.
        $this->processLitterTags($category, $litterObject, $value);
    }

    /**
     * Attach a material to the contextual pivot (category, litter object).
     *
     * @param  Category      $category
     * @param  LitterObject  $litterObject
     * @param  Materials     $material
     */
    protected function attachMaterialToPivot(Category $category, LitterObject $litterObject, Materials $material): void
    {
        $pivot = CategoryLitterObject::firstOrCreate([
            'category_id'      => $category->id,
            'litter_object_id' => $litterObject->id,
        ]);

        $pivot->materials()->syncWithoutDetaching([$material->id]);
    }
}
