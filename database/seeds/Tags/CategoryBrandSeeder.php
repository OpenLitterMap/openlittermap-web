<?php

namespace Database\Seeders\Tags;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use Illuminate\Database\Seeder;

class CategoryBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = Brand::types();

        foreach ($brands as $brand) {
            BrandList::firstOrCreate([
                'key' => $brand
            ]);
        }

        // Category => Brands?
        // LitterObject => Brands?
        // TagType => Brands?
        $alcoholBrands = [
            'amstel',
            'bacardi',
            'carlsberg',
            'corona',
            'diageo',
            'fosters',
            'guinness',
            'heineken',
            'lindenvillage',
            'stella',
        ];

        $alcohol = Category::where('key', 'alcohol')->first()->id;

        $automobileBrands = [
            'audi',
            'bmw',
            'ford',
            'honda',
            'hyundai',
            'kia',
            'mercedes',
            'nissan',
            'peugeot',
            'renault',
            'toyota',
            'volkswagen',
        ];

        $clothingBrands = [
            'adidas',
            'dunnes',
            'nike',
            'reebok',
            'puma',
            ];

        $coffeeBrands = [
            'bewleys',
            'costa',
            'butlers',
            'cafe_nero',
            'frank_and_honest',
            'insomnia',
            'nescafe',
            'tim_hortons',
        ];

        $electronicBrands = [
            'apple',
            'duracell',
            'samsung',
        ];

        $foodBrands = [
            'burgerking',
            'cadburys',
            'dunnes',
            'mcdonalds',
            'butlers',
            'cadburys',
            'centra',
            'doritos',
            'fritolay',
            'haribo',
            'kellogs',
            'kfc',
            'nestle',
            'obriens',
            'subway',
            'supermacs',
            'tayto',
            'thins',
            'walkers',
            'wrigleys',
        ];

        $sanitaryBrands = [
            'colgate',
            'durex',
            'gillette',
            'loreal',
        ];

        $smokingBrands = [
            'camel',
            'marlboro',
        ];

        $softDrinkBrands = [
            'ballygowan',
            'budweiser',
            'bulmers',
            'caprisun',
            'cocacola',
            'drpepper',
            'evian',
            'fanta',
            'gatorade',
            'lipton',
            'lucozade',
            'magners',
            'mars',
            'monster',
            'pepsi',
            'redbull',
            'powerade',
            'ribena',
            'volvic',
        ];

        $stationaryBrands = [
            'bic',
        ];
    }
}
