<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Category => Brands?
        // LitterObject => Brands?
        // TagType => Brands?
        $alcoholBrands = [
            'diageo',
            'guinness',
            'heineken',
        ];

        $softDrinkBrands = [
            'cocacola',
            'pepsi',
            'redbull'
        ];
    }
}
