<?php

namespace Database\Seeders;

use App\Models\Litter\Tags\LitterModel;
use App\Models\Photo;
use App\Models\Litter\Tags\TagType;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Categories\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LitterModelSeeder extends Seeder
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
        // or
        // Category => LitterObject[] => TagType[] => [ Morphy Many Material[] ]
        // or
        // LitterModel => Materials[]

        $categoryTags = [

            // Category
            'alcohol' => [
                // Object
                'bottle' => [
                    // TagType => Material
                    'beer' => ['material:glass'],
                    'cider' => ['material:glass', 'material:plastic'],
                    'spirits' => ['material:glass'],
                    'wine' => ['material:glass']
                ],

                // Object => Material
                'bottleTop' => ['material:metal', 'material:plastic', 'material:cork'],
                'brokenGlass' => ['material:glass'],
                'can' => [
                    'beer' => ['material:aluminium'],
                    'spirits' => ['material:aluminium'],
                    'cider' => ['material:aluminium'],
                ],
                'cup' => ['material:plastic'],
                'drinkingGlass' => [
                    'wineGlass' => ['material:glass'],
                    'pintGlass' => ['material:glass'],
                    'shotGlass' => ['material:glass'],
                ],
                'packaging' => [
                    'box' => ['material:cardboard'],
                    'label' => ['material:paper', 'material:plastic'],
                ],
                'pullRing' => ['material:aluminium'],
                'straw' => ['material:plastic', 'material:paper', 'material:metal'],
                'sixPackRings' => ['material:plastic'],
                'other'
            ],

            'automobile' => [
                'battery' => ['material:metal', 'material:plastic'],
                'carPart' => [
                    'alloy' => ['material:metal'],
                    'bumper' => ['material:plastic', 'material:metal'],
                    'exhaust' => ['material:metal'],
                    'engine' => ['material:metal'],
                    'mirror' => ['material:glass', 'material:plastic'],
                    'light' => ['material:glass', 'material:plastic'],
                    'licensePlate' => ['material:metal', 'material:plastic'],
                ],
                'fuelContainer' => ['material:plastic', 'material:metal'],
                'oilCan' => ['material:metal', 'material:plastic'],
                'tyre' => ['material:rubber'],
                'wheel' => ['material:metal'],
                'other'
            ],

            'clothing' => [
                'shirt' => [
                    'tShirt' => ['material:cotton', 'material:polyester', 'material:blend'],
                    'dressShirt' => ['material:cotton', 'material:linen', 'material:silk'],
                    'sweatshirt' => ['material:cotton', 'material:polyester', 'material:fleece'],
                    'blouse' => ['material:cotton', 'material:silk', 'material:polyester'],
                ],
                'pants' => [
                    'jeans' => ['material:denim', 'material:cotton', 'material:stretchDenim'],
                    'trousers' => ['material:cotton', 'material:wool', 'material:polyester'],
                    'shorts' => ['material:cotton', 'material:polyester', 'material:nylon'],
                    'leggings' => ['material:spandex', 'material:nylon', 'material:polyester'],
                ],
                'shoes' => [
                    'sneakers' => ['material:leather', 'material:synthetic', 'material:canvas'],
                    'boots' => ['material:leather', 'material:rubber', 'material:synthetic'],
                    'sandals' => ['material:leather', 'material:rubber', 'material:synthetic'],
                    'heels' => ['material:leather', 'material:synthetic'],
                ],
                'hat' => [
                    'baseballCap' => ['material:cotton', 'material:polyester'],
                    'beanie' => ['material:wool', 'material:acrylic', 'material:cotton'],
                    'sunHat' => ['material:straw', 'material:cotton', 'material:paper'],
                ],
                'jacket' => [
                    'raincoat' => ['material:nylon', 'material:polyester', 'material:pvc'],
                    'leatherJacket' => ['material:leather'],
                    'denimJacket' => ['material:denim', 'material:cotton'],
                    'fleeceJacket' => ['material:fleece', 'material:polyester'],
                ],
                'underwear' => [
                    'briefs' => ['material:cotton', 'material:spandex', 'material:modal'],
                    'boxers' => ['material:cotton', 'material:silk', 'material:bamboo'],
                    'socks' => ['material:cotton', 'material:wool', 'material:synthetic'],
                    'bra' => ['material:cotton', 'material:spandex', 'material:nylon'],
                ],
                'accessories' => [
                    'belt' => ['material:leather', 'material:synthetic', 'material:canvas'],
                    'scarf' => ['material:wool', 'material:cotton', 'material:silk', 'material:acrylic'],
                    'tie' => ['material:silk', 'material:polyester', 'material:cotton'],
                    'gloves' => ['material:leather', 'material:wool', 'material:synthetic'],
                ],
                'dress' => [
                    'material:cotton',
                    'material:polyester',
                    'material:linen',
                    'material:silk',
                    'material:satin',
                ],
                'swimwear' => [
                    'swimsuit' => ['material:nylon', 'material:spandex', 'material:polyester'],
                    'trunks' => ['material:nylon', 'material:polyester'],
                    'bikini' => ['material:nylon', 'material:spandex'],
                ],
                'sleepwear' => [
                    'pajamas' => ['material:cotton', 'material:silk', 'material:flannel'],
                    'nightgown' => ['material:cotton', 'material:silk', 'material:polyester'],
                ],
                'other',
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
                'degraded_plasticbottle' => ['material:plastic'],
                'degraded_plasticbag' => ['material:plastic'],
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
                'cup' => [
                    'material:paper',
                    'material:plastic',
                    'material:foam',
                    'material:ceramic',
                    'material:metal',
                ],
                'lid' => ['material:plastic', 'material:paper', 'material:bioplastic', 'material:plantFiber'],
                'stirrer' => ['material:wood', 'material:plastic','material:metal','material:bamboo'],
                'packaging' => [
                    'coffeeBag' => ['material:plastic', 'material:foil', 'material:paper'],
                    'singleServePacket' => ['material:plastic', 'material:foil'],
                    'pod' => ['material:plastic', 'material:aluminium'],
                    'sachet' => ['material:paper', 'material:plastic', 'material:foil'],
                ],
                'sleeves' => ['material:cardboard', 'material:silicone'],
                'other'
            ],

            'electronics' => [
                'battery' =>  ['material:metal'],
                'cable' => ['material:plastic', 'material:copper'],
                'device' => [
                    'mobilePhone' => ['material:metal', 'material:plastic', 'material:glass'],
                    'laptop' => ['material:metal', 'material:plastic', 'material:glass'],
                    'tablet' => ['material:metal', 'material:plastic', 'material:glass'],
                    'charger' => ['material:plastic', 'material:metal'],
                ],
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
                'crisps' => [
                    'cripsSmall' => ['material:foil'],
                    'crispsLarge' => ['material:foil'],
                ],
                'plate' => [
                    'material:plastic',
                    'material:paper',
                    'material:foam',
                    'material:ceramic',
                    'material:metal',
                    'material:glass',
                    'material:bioplastic',
                ],
                'napkin' => [
                    'material:paper',
                    'material:cloth',
                    'material:biodegradable',
                ],
                'jar' => [
                    'material:glass',
                    'material:plastic',
                    'material:metal',
                ],
                'lid' => [
                    'material:ceramic',
                    'material:metal',
                    'material:plastic',
                    'material:glass',
                ],
                'tinfoil' => ['material:aluminium'],
                'box' => [
                    'material:cardboard',
                    'material:plastic',
                    'material:wood',
                    'material:metal',
                ],
                'gum' => [
                    'material:rubber',
                ],
                'bag' => [
                    'material:plastic',
                    'material:paper',
                    'material:cloth',
                    'material:bioplastic',
                ],
                'can' => [
                    'material:aluminium',
                    'material:steel',
                ],
                'other',
            ],

            'industrial' => [
                // Object: "container"
                'container' => [
                    // Tag Types
                    'oilDrum'     => ['material:metal', 'material:plastic'],
                    'chemicalBag' => ['material:plastic', 'material:paper'],
                ],

                // Object: "oil" + materials
                'oil' => ['material:oil'],

                // Object: "chemical" + materials
                'chemical' => ['material:chemical'],

                // Object: "industrialPlastic"
                'industrialPlastic' => [
                    'plasticPellets'   => ['material:plastic'],
                    'plasticSheeting'  => ['material:plastic'],
                    'plasticPipe'      => ['material:plastic'],
                    'plasticContainer' => ['material:plastic'],
                    'plasticWrapping'  => ['material:plastic'],
                ],

                // Object: "construction"
                'construction' => [
                    'brick'         => ['material:clay', 'material:concrete'],
                    'cementBag'     => ['material:paper', 'material:plastic'],
                    'concreteBlock' => ['material:concrete'],
                    'rebar'         => ['material:metal'],
                    'insulation'    => ['material:fiberglass', 'material:foam'],
                    'asphalt'       => ['material:asphalt'],
                    'tile'          => ['material:ceramic', 'material:stone'],
                    'drywall'       => ['material:gypsum', 'material:paper'],
                ],

                // Object: "tape" + materials
                'tape' => ['material:plastic', 'material:adhesive'],

                // Object: "pallet"
                'pallet' => [
                    'woodenPallet'  => ['material:wood'],
                    'plasticPallet' => ['material:plastic'],
                ],

                // Object: "strapping"
                'strapping' => [
                    'plasticStrapping' => ['material:plastic'],
                    'metalStrapping'   => ['material:metal'],
                ],

                // Object: "wire"
                'wire' => [
                    'copperWire' => ['material:copper', 'material:plastic'],
                    'steelWire'  => ['material:steel'],
                ],

                // Object: "hose"
                'hose' => [
                    'rubberHose'  => ['material:rubber'],
                    'plasticHose' => ['material:plastic'],
                ],

                // Object: "pipe"
                'pipe' => [
                    'metalPipe'     => ['material:metal'],
                    'plasticPipe'   => ['material:plastic'],
                    'concretePipe'  => ['material:concrete'],
                ],

                // Object: "industrialContainer"
                'industrialContainer' => [
                    'material:metal',
                    'material:plastic',
                ],

                // Object: "other" (no tag types or materials)
                'other',
            ],

            'other' => [
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
                'life_buoy', // coastal?
                'furniture',
                'mattress',
                'appliance',
                'paintCan',
                'other',
                'graffiti',
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
                'condoms' => [
                    'material:latex',
                    'condomWrapper' => ['material:plastic', 'material:foil'],
                ],
                'nappies' => [
                    'material:plastic',
                    'material:cloth',
                ],
                'menstrual' => [
                    'sanitaryPad' => ['material:cotton', 'material:plastic'],
                    'tampon' => ['material:plastic'],
                ],
                'deodorant' => [
                    'rollOn' => ['material:plastic', 'material:aluminium'],
                    'stick' => ['material:plastic'],
                    'sprayCan' => ['material:aluminium'],
                ],
                'earSwabs' => [
                    'material:plastic',
                    'material:cotton',
                    'material:paper',
                ],
                'oralHygiene' => [
                    'toothbrush' => ['material:plastic', 'material:nylon', 'material:bamboo', 'material:wood'],
                    'toothpasteTube' => ['material:plastic', 'material:aluminium'],
                    'toothpasteBox' => ['material:cardboard'],
                    'dentalFloss' => ['material:nylon', 'material:plastic'],
                    'mouthwashBottle' => ['material:plastic', 'material:glass'],
                ],
                'wipes' => [
                    'babyWipes' => ['material:fabric', 'material:plastic'],
                    'antibacterialWipes' => ['material:fabric', 'material:plastic'],
                    'makeupRemoverWipes' => ['material:fabric', 'material:plastic'],
                    'flushableWipes' => ['material:fabric', 'material:plastic'],
                ],
                'sanitiser' => ['material:plastic'],
                'medical' => [
                    'syringe' => ['material:plastic', 'material:metal'],
                    'bandage' => ['material:cotton', 'material:elastic'],
                    'plaster' => ['material:plastic', 'material:adhesive'],
                    'medicineBottle' => ['material:plastic', 'material:glass'],
                    'pillPack' => ['material:plastic', 'material:aluminium'],
                ],
                'other'
            ],

            'smoking' => [
                'butts' => ['material:plastic', 'material:paper', 'material:biodegradable'],
                'lighters' => ['material:plastic', 'material:metal'],
                'box' => [
                    'cigaretteBox' => ['material:cardboard'],
                    'matchBox' => ['material:cardboard']
                ],
                'pouch' => [
                    'tobaccoPouch' => ['material:plastic']
                ],
                'rollingPapers' => ['material:paper'],
                'packaging' => ['material:cellophane', 'material:foil'],
                'filters' => ['material:plastic', 'material:biodegradable'],
                'vape' => [
                    'vapePen' => ['material:plastic', 'material:metal'],
                    'vapeOil' => ['material:plastic', 'material:glass'],
                ],
                'paraphernalia' => [
                    'pipe' => ['material:glass', 'material:metal', 'material:ceramic'],
                    'bong' => ['material:glass', 'material:metal', 'material:ceramic'],
                    'grinder' => ['material:metal', 'material:plastic'],
                ],
                'ashtray' => ['material:glass', 'material:ceramic', 'material:metal'],
                'other'
            ],

            'softdrinks' => [
                'bottle' => [
                    'water' => ['material:plastic', 'material:glass'],
                    'soda' => ['material:plastic', 'material:glass'],
                    'juice' => ['material:plastic', 'material:glass'],
                    'energyDrink' => ['material:plastic', 'material:glass'],
                    'sportsDrink' => ['material:plastic', 'material:glass'],
                    'icedTea' => ['material:plastic', 'material:glass'],
                    'milk' => ['material:plastic', 'material:glass'],
                    'smoothie' => ['material:plastic', 'material:glass'],
                ],
                'can' => [
                    'soda' => ['material:aluminium'],
                    'energyDrink' => ['material:aluminium'],
                    'juice' => ['material:aluminium'],
                    'icedTea' => ['material:aluminium'],
                    'coffee' => ['material:aluminium'],
                    'sparklingWater' => ['material:aluminium'],
                ],
                'carton' => [
                    'juice' => ['material:cardboard', 'material:foil', 'material:plastic'],
                    'milk' => ['material:cardboard', 'material:foil', 'material:plastic'],
                    'icedTea' => ['material:cardboard', 'material:foil', 'material:plastic'],
                    'plantMilk' => ['material:cardboard', 'material:foil', 'material:plastic'],
                ],
                'cup' => [
                    'material:plastic',
                    'material:paper',
                    'material:foam',
                ],
                'drinkingGlass' => ['material:glass'],
                'brokenGlass' => ['material:glass'],
                'lid' => ['material:plastic'],
                'label' => [
                    'material:paper',
                    'material:plastic',
                ],
                'pullRing' => ['material:aluminium'],
                'packaging' => [
                    'sixPackRing' => ['material:plastic'],
                    'shrinkWrap' => ['material:plastic'],
                    'box' => ['material:cardboard'],
                    'tray' => ['material:plastic'],
                ],
                'straw' => [
                    'material:plastic',
                    'material:paper',
                    'material:metal',
                    'material:bamboo',
                ],
                'pouch' => [
                    'juice' => ['material:plastic', 'material:foil'],
                ],
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
                'cableTie' => ['material:plastic'],
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
     * @param  Category                  $category
     * @param  LitterObject|TagType|null $parent   (null => creating LitterObjects,
     *                                             LitterObject => adding TagTypes/materials,
     *                                             TagType => attach materials or go deeper)
     * @param  mixed                     $data     (could be nested arrays or single strings).
     * @param  LitterObject|null         $objectContext Keep track of the current object when $parent is TagType
     */
    protected function processLitterTags(
        Category $category,
        LitterObject|TagType|null $parent,
        mixed $data,
        LitterObject|null $objectContext = null
    ): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_int($key)) {
                    // The value is a string => "other", or "material:xxx"
                    $this->handleStringItem($category, $parent, $value, $objectContext);
                } else {
                    // $key is something like "bottle", "can", "beer"
                    $this->handleArrayItem($category, $parent, $key, $value, $objectContext);
                }
            }
        } else {
            // $data is a single string
            $this->handleStringItem($category, $parent, $data, $objectContext);
        }
    }

    /**
     * Handle a single string item, which may be:
     *  - "other" => a LitterObject if $parent is null
     *  - "material:xxx" => attach material to the parent
     */
    protected function handleStringItem(
        Category $category,
        LitterObject|TagType|null $parent,
        string $item,
        LitterObject|null $objectContext
    ): void
    {
        if (str_starts_with($item, 'material:')) {
            // It's a material
            $materialKey = substr($item, strlen('material:'));
            $material = Materials::firstOrCreate(['key' => $materialKey]);

            // Attach to the parent polymorphically (global) + pivot (contextual)
            if ($parent) {
                // 1) Polymorphic attach => "global" materials
                $parent->materials()->syncWithoutDetaching([$material->id]);

                // 2) Pivot-based attach => "contextual" materials
                $this->attachMaterialToPivot($category, $parent, $material, $objectContext);
            }

        } else {
            // It's presumably a LitterObject name or TagType name
            if ($parent === null) {
                // => LitterObject, no parent
                $object = LitterObject::firstOrCreate(['key' => $item]);

                // Insert a pivot row with tag_type_id=null
                LitterModel::firstOrCreate([
                    'category_id'      => $category->id,
                    'litter_object_id' => $object->id,
                    'tag_type_id'      => null,
                ]);

            } elseif ($parent instanceof LitterObject) {
                // => The string is a TagType name
                $tagType = TagType::firstOrCreate(['key' => $item]);
                $parent->tagTypes()->syncWithoutDetaching([
                    $tagType->id => ['category_id' => $category->id]
                ]);

            } elseif ($parent instanceof TagType) {
                // deeper nesting if needed
            } else {
                Log::warning("Unknown parent type in handleStringItem", [
                    'parent_class' => get_class($parent),
                    'item' => $item
                ]);
            }
        }
    }

    /**
     * Handle an array item with key => value, e.g.:
     *  "bottle" => ["beer" => ["material:glass"], ...]
     *  "can"    => ["beer" => ["material:aluminium"]]
     *
     * If $parent === null => The key is a LitterObject
     * If $parent is a LitterObject => The key is a TagType
     */
    protected function handleArrayItem(
        Category $category,
        LitterObject|TagType|null $parent,
        string $key,
        array $value,
        LitterObject|null $objectContext
    ): void
    {
        if ($parent === null) {
            // => LitterObject
            $litterObject = LitterObject::firstOrCreate(['key' => $key]);

            // If no sub-TagTypes => insert pivot row with tag_type_id=null
            if (!$this->arrayContainsTagTypes($value)) {
                LitterModel::firstOrCreate([
                    'category_id'      => $category->id,
                    'litter_object_id' => $litterObject->id,
                    'tag_type_id'      => null,
                ]);
            }

            // Recurse => now $parent is the LitterObject
            $this->processLitterTags($category, $litterObject, $value, $litterObject);

        } elseif ($parent instanceof LitterObject) {
            // => The key is a TagType
            $tagType = TagType::firstOrCreate(['key' => $key]);

            // (cat, object, tag) pivot
            $parent->tagTypes()->syncWithoutDetaching([
                $tagType->id => ['category_id' => $category->id]
            ]);

            // Recurse => now $parent is TagType, but keep the $objectContext as $parent
            $this->processLitterTags($category, $tagType, $value, $parent);

        } elseif ($parent instanceof TagType) {
            // deeper nesting
            $this->processLitterTags($category, $parent, [$key => $value], $objectContext);
        }
    }

    /**
     * Attach $material to the triple pivot row that corresponds to (category, $parent, $objectContext).
     */
    protected function attachMaterialToPivot(
        Category $category,
        LitterObject|TagType $parent,
        Materials $material,
        LitterObject|null $objectContext
    ): void
    {
        // 1) If parent is a LitterObject => (cat, object, null)
        if ($parent instanceof LitterObject) {
            $litterModel = LitterModel::firstOrCreate([
                'category_id'      => $category->id,
                'litter_object_id' => $parent->id,
                'tag_type_id'      => null,
            ]);
            $litterModel->modelMaterials()->syncWithoutDetaching([$material->id]);
            return;
        }

        // 2) If parent is a TagType => we need the objectContext to find (cat, object, tag).
        if (!$objectContext) {
            Log::warning("No object context to attach materials for TagType {$parent->key}");
            return;
        }

        $litterModel = LitterModel::firstOrCreate([
            'category_id'      => $category->id,
            'litter_object_id' => $objectContext->id,
            'tag_type_id'      => $parent->id,
        ]);

        // Attach material
        $litterModel->modelMaterials()->syncWithoutDetaching([$material->id]);
    }

    /**
     * Decide if an array is purely "materials" or if it has subkeys => TagTypes.
     */
    protected function arrayContainsTagTypes(array $value): bool
    {
        foreach ($value as $k => $v) {
            // If the key is NOT an integer => TagType name
            if (!is_int($k)) {
                return true;
            }
            // If the key is integer but $v is not "material:..."
            if (is_string($v) && !str_starts_with($v, 'material:')) {
                return true;
            }
            // If $v is an array => likely TagTypes
            if (is_array($v)) {
                return true;
            }
        }
        return false;
    }
}
