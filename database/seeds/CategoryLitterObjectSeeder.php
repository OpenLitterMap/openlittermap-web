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

        $materials = Material::types();

        foreach ($materials as $material) {
            Materials::firstOrCreate([
                'key' => $material,
            ]);
        }

        $categoryTags = [
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
            'alcohol' => [
                'bottle' => [
                    'beer' => ['material:glass'],
                    'wine' => ['material:glass'],
                    'spirits' => ['material:glass'],
                    'cider' => ['material:glass', 'material:plastic']
                ],
                'can' => [
                    'beer' => ['material:aluminium'],
                    'spirits' => ['material:aluminium'],
                    'cider' => ['material:aluminium'],
                ],
                'packaging' => [
                    'box' => ['material:cardboard'],
                    'label' => ['material:paper', 'material:plastic'],
                ],
                'drinkingGlass' => [
                    'wineGlass' => ['material:glass'],
                    'pintGlass' => ['material:glass'],
                    'shotGlass' => ['material:glass'],
                ],
                'cup' => ['material:plastic', 'material:paper', 'material:foam'],
                'straw' => ['material:plastic', 'material:paper', 'material:metal'],
                'brokenGlass' => ['material:glass'],
                'bottleTop' => ['material:metal', 'material:plastic', 'material:cork'],
                'sixPackRings' => ['material:plastic'],
                'pullRing' => ['material:aluminium'],
                'other'
            ],
            'coffee' => [
                'cup' => [
                    'material:paper',
                    'material:plastic',
                    'material:foam',
                    'material:ceramic',
                    'material:metal',
                    'material:plastic',
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
                    'cripsSmall' => ['material:plastic'],
                    'crispsLarge' => ['material:plastic'],
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
                'napkins',
                'jar',
                'lid',
                'aluminium',
                'box',
                'gum',
                'bags',
                'cans',
                'other',
            ],
            'softdrinks' => [
                'bottle',
                'can',
                'lid',
                'label',
                'straws',
                'cup',
                'carton',
                'packet',
                'pullRing',
                'packaging',
                'cup',
                'drinkingGlass',
                'brokenGlass',
                'other'
            ],
            'sanitary' => [
                'gloves',
                'facemask',
                'condoms',
                'nappies',
                'menstral',
                'deodorant',
                'earSwabs',
                'oralHygiene',
                'wipes',
                'sanitiser',
                'medical',
                'other'
            ],
            'other' => [
                'randomLitter',
                'bagsLitter',
                'overflowingBins',
                'plastic',
                'automobile',
                'tyre',
                'trafficCone',
                'metal',
                'plasticBags',
                'posters',
                'cableTie',
                'books',
                'magazine',
                'paper',
                'stationary',
                'washingUp',
                'clothing',
                'hairTie',
                'earPlugs',
                'electric',
                'batteries',
                'balloons',
                'life_buoy', // coastal?
                'furniture',
                'mattress',
                'appliance',
                'can', // paint
                'other',
                'graffiti',
            ],
            'dogshit' => [
                'poo',
                'poo_in_bag'
            ],
        ];

        foreach ($categoryTags as $litterCategory => $litterTags)
        {
            $category = Category::where('key', $litterCategory)->first();

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
                    $material = Material::firstOrCreate(['key' => $materialKey]);

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

                $material = Material::firstOrCreate(['key' => $materialKey]);

                $parentObject->materials()->syncWithoutDetaching([$material->id]);
            }
        }
    }
}
