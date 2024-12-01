<?php

namespace Database\Seeders;

use App\Models\Litter\Categories\Material;
use App\Models\LitterObject;
use App\Models\Materials;
use App\Models\Photo;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryLitterObjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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

        $tags = [
            'smoking' => [
                'butts',
                'lighters',
                'box',
                'pouch',
                'papers',
                'packaging',
                'filters',
                'filterbox',
                'vape_pen',
                'vape_oil',
                'smokingOther'
            ],
            'alcohol' => [
                'beerBottle',
                'spiritBottle',
                'wineBottle',
                'beerCan',
                'brokenGlass',
                'bottleTops',
                'paperCardAlcoholPackaging',
                'plasticAlcoholPackaging',
                'pint',
                'six_pack_rings',
                'alcohol_plastic_cups',
                'alcoholOther'
            ],
            'coffee' => [
                'coffeeCups',
                'coffeeLids',
                'coffeeOther'
            ],
            'food' => [
                'sweetWrappers',
                'paperFoodPackaging',
                'plasticFoodPackaging',
                'plasticCutlery',
                'crisp_small',
                'crisp_large',
                'styrofoam_plate',
                'napkins',
                'sauce_packet',
                'glass_jar',
                'glass_jar_lid',
                'aluminium_foil',
                'pizza_box',
                'foodOther',
                'chewing_gum'
            ],
            'softdrinks' => [
                'waterBottle',
                'fizzyDrinkBottle',
                'tinCan',
                'bottleLid',
                'bottleLabel',
                'sportsDrink',
                'straws',
                'plastic_cups',
                'plastic_cup_tops',
                'milk_bottle',
                'milk_carton',
                'paper_cups',
                'juice_cartons',
                'juice_bottles',
                'juice_packet',
                'ice_tea_bottles',
                'ice_tea_can',
                'energy_can',
                'pullring',
                'strawpacket',
                'styro_cup',
                'broken_glass',
                'softDrinkOther'
            ],
            'sanitary' => [
                'gloves',
                'facemask',
                'condoms',
                'nappies',
                'menstral',
                'deodorant',
                'ear_swabs',
                'tooth_pick',
                'tooth_brush',
                'wetwipes',
                'hand_sanitiser',
                'sanitaryOther'
            ],
            'other' => [
                'random_litter',
                'bags_litter',
                'overflowing_bins',
                'plastic',
                'automobile',
                'tyre',
                'traffic_cone',
                'metal',
                'plastic_bags',
                'election_posters',
                'forsale_posters',
                'cable_tie',
                'books',
                'magazine',
                'paper',
                'stationary',
                'washing_up',
                'clothing',
                'hair_tie',
                'ear_plugs',
                'elec_small',
                'elec_large',
                'batteries',
                'balloons',
                'life_buoy', // coastal?
                'other'
            ],
            'dogshit' => [
                'poo',
                'poo_in_bag'
            ],
        ];

        foreach ($tags as $category => $tags) {
            $category = Category::where('key', $category)->first();

            $litterObjectIds = [];

            foreach ($tags as $tag) {

                $litterObject = LitterObject::firstOrCreate([ 'key' => $tag ]);

                $litterObjectIds[] = $litterObject->id;
            }

            $category->litterObjects()->syncWithoutDetaching($litterObjectIds);
        }
    }
}
