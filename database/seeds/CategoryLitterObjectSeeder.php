<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\TagType;
use App\Models\Category;
use App\Models\Materials;
use App\Models\LitterObject;
use Illuminate\Database\Seeder;
use App\Models\Litter\Categories\Material;

class CategoryLitterObjectSeeder extends Seeder
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

        // Category => LitterObject => Material, or
        // Category => LitterObject => TagType => Material

        $categoryTags = [

            'alcohol' => [
                'bottle' => [
                    'beer' => ['material:glass'],
                    'cider' => ['material:glass', 'material:plastic'],
                    'spirits' => ['material:glass'],
                    'wine' => ['material:glass']
                ],
                'bottleTop' => ['material:metal', 'material:plastic', 'material:cork'],
                'brokenGlass' => ['material:glass'],
                'can' => [
                    'beer' => ['material:aluminium'],
                    'spirits' => ['material:aluminium'],
                    'cider' => ['material:aluminium'],
                ],
                'cup' => ['material:plastic', 'material:paper', 'material:foam'],
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
                    'casualDress' => ['material:cotton', 'material:polyester', 'material:linen'],
                    'eveningDress' => ['material:silk', 'material:satin', 'material:chiffon'],
                    'cocktailDress' => ['material:silk', 'material:polyester', 'material:lace'],
                ],
                'suit' => [
                    'businessSuit' => ['material:wool', 'material:polyester', 'material:linen'],
                    'tuxedo' => ['material:wool', 'material:satin'],
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
                    'material:glass'
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
                'container' => [
                    'oilDrum' => ['material:metal', 'material:plastic'],
                    'chemicalBag' => ['material:plastic', 'material:paper'],
                ],
                'oil' => ['material:oil'],
                'chemical' => ['material:chemical'],
                'industrialPlastic' => [
                    'plasticPellets' => ['material:plastic'],
                    'plasticSheeting' => ['material:plastic'],
                    'plasticPipe' => ['material:plastic'],
                    'plasticContainer' => ['material:plastic'],
                    'plasticWrapping' => ['material:plastic'],
                ],
                'construction' => [
                    'brick' => ['material:clay', 'material:concrete'],
                    'cementBag' => ['material:paper', 'material:plastic'],
                    'concreteBlock' => ['material:concrete'],
                    'rebar' => ['material:metal'],
                    'insulation' => [
                        'fiberglassInsulation' => ['material:fiberglass'],
                        'foamInsulation' => ['material:foam'],
                    ],
                    'asphalt' => ['material:asphalt'],
                    'tile' => ['material:ceramic', 'material:stone'],
                    'drywall' => ['material:gypsum', 'material:paper'],
                ],
                'tape' => ['material:plastic', 'material:adhesive'],
                'industrialOther' => [
                    'pallet' => [
                        'woodenPallet' => ['material:wood'],
                        'plasticPallet' => ['material:plastic'],
                    ],
                    'strapping' => [
                        'plasticStrapping' => ['material:plastic'],
                        'metalStrapping' => ['material:metal'],
                    ],
                    'wire' => [
                        'copperWire' => ['material:copper', 'material:plastic'],
                        'steelWire' => ['material:steel'],
                    ],
                    'hose' => [
                        'rubberHose' => ['material:rubber'],
                        'plasticHose' => ['material:plastic'],
                    ],
                    'pipe' => [
                        'metalPipe' => ['material:metal'],
                        'plasticPipe' => ['material:plastic'],
                        'concretePipe' => ['material:concrete'],
                    ],
                    'industrialContainer' => [
                        'material:metal',
                        'material:plastic',
                    ],
                ],
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

        foreach ($categoryTags as $litterCategory => $litterTags)
        {
            $category = Category::firstOrCreate(['key' => $litterCategory]);

            $this->processLitterTags($category, $litterTags);
        }
    }

    protected function processLitterTags (Category $category, array $tags, $parentObject = null): void
    {
        foreach ($tags as $key => $value)
        {
            if (is_array($value))
            {
                // The key is either a LitterObject or TagType
                $itemKey = $key;

                if ($parentObject === null) {
                    // We're at the LitterObject level
                    $litterObject = LitterObject::firstOrCreate(['key' => $itemKey]);
                    $category->litterObjects()->syncWithoutDetaching([$litterObject->id]);

                    // Recursively process the next level
                    $this->processLitterTags($category, $value, $litterObject);
                }
                elseif ($parentObject instanceof LitterObject)
                {
                    // We're at the TagType level
                    $tagType = TagType::firstOrCreate(['key' => $itemKey]);
                    $parentObject->tagTypes()->syncWithoutDetaching([$tagType->id]);

                    // Recursively process the next level
                    $this->processLitterTags($category, $value, $tagType);
                }
                elseif ($parentObject instanceof TagType)
                {
                    // We're at a deeper level (unlikely based on your data)
                    // Process materials if present
                    $this->processMaterials($value, $parentObject);
                }
            }
            else
            {
                // The value is a string, could be a LitterObject, TagType, or Material
                $item = is_int($key) ? $value : $key;

                if (strpos($item, 'material:') === 0)
                {
                    // It's a material
                    $materialKey = substr($item, strlen('material:'));
                    $material = Materials::firstOrCreate(['key' => $materialKey]);

                    if ($parentObject) {
                        $parentObject->materials()->syncWithoutDetaching([$material->id]);
                    }
                }
                else
                {
                    if ($parentObject === null)
                    {
                        // LitterObject without TagTypes
                        $litterObject = LitterObject::firstOrCreate(['key' => $item]);

                        $category->litterObjects()->syncWithoutDetaching([$litterObject->id]);
                    }
                    elseif ($parentObject instanceof LitterObject)
                    {
                        // TagType without materials
                        $tagType = TagType::firstOrCreate(['key' => $item]);

                        $parentObject->tagTypes()->syncWithoutDetaching([$tagType->id]);
                    }
                }
            }
        }
    }

    protected function processMaterials (array $materials, $parentObject)
    {
        foreach ($materials as $materialEntry)
        {
            if (strpos($materialEntry, 'material:') === 0)
            {
                $materialKey = substr($materialEntry, strlen('material:'));

                $material = Materials::firstOrCreate(['key' => $materialKey]);

                $parentObject->materials()->syncWithoutDetaching([$material->id]);
            }
        }
    }
}
