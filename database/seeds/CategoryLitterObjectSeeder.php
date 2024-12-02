<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\Category;
use App\Models\Materials;
use App\Models\LitterObject;
use App\Models\TagType;
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
                'butts',
                'lighters',
                'box' => ['cigaretteBox', 'matchBox'],
                'pouch' => ['tobaccoPouch'],
                'papers',
                'packaging' => ['cellophane', 'foil'],
                'filters',
                'vape' => ['vapePen', 'vapeOil'],
                'paraphernalia' => ['pipe', 'bong', 'grinder'],
                'ashtray',
                'other'
            ],
            'alcohol' => [
                'bottle' => ['beer', 'wine', 'spirits', 'cider'],
                'can' => ['beer', 'wine', 'spirits', 'cider'],
                'packaging' => ['box', 'label'],
                'glass' => ['wineGlass', 'pintGlass', 'shotGlass'],
                'cup',
                'straw',
                'brokenGlass',
                'bottleTop',
                'sixPackRings',
                'pullRing',
                'other'
            ],
            'coffee' => [
                'cup' => ['paperCup', 'plasticCup', 'styrofoamCup', 'reusableCup'],
                'lid' => ['plasticLid', 'paperLid', 'compostableLid'],
                'stirrer' => ['woodenStirrer', 'plasticStirrer', 'metalStirrer'],
                'packaging' => ['coffeeBag', 'singleServePacket'],
                'sleeves' => ['cardboardSleeve', 'reusableSleeve'],
                'other'
            ],
            'food' => [
                'wrapper' => ['sweetWrapper', 'chocolateWrapper'],
                'packet' => ['saucePacket'],
                'packaging' => ['plasticPackaging', 'paperPackaging', 'foamPackaging'],
                'cutlery' => ['plasticCutlery', 'woodenCutlery', 'biodegradableCutlery'],
                'crisps' => ['cripsSmall', 'crispsLarge'],
                'plate' => ['paperPlate', 'plasticPlate', 'foamPlate'],
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
                'glass',
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

            $litterObjectIds = [];

            foreach ($litterTags as $tag => $tagTypes)
            {
                // Handle items without TagTypes
                if (is_int($tag)) {
                    $tag = $tagTypes;
                    $tagTypes = [];
                }

                $litterObject = LitterObject::firstOrCreate([ 'key' => $tag ]);

                if (!empty($tagTypes))
                {
                    $tagTypeIds = [];

                    foreach ($tagTypes as $tagType)
                    {
                        $tagType = TagType::firstOrCreate([ 'key' => $tagType ]);
                        $tagTypeIds[] = $tagType->id;
                    }

                    $tag->tagTypes()->syncWithoutDetaching($tagTypeIds);
                }

                $litterObjectIds[] = $litterObject->id;
            }

            $category->litterObjects()->syncWithoutDetaching($litterObjectIds);
        }
    }
}
