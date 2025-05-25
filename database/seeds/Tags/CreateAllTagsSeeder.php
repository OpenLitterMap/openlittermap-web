<?php

namespace Database\Seeders\Tags;

use Database\Seeders\AchievementsSeeder;
use Illuminate\Database\Seeder;

class CreateAllTagsSeeder extends Seeder
{
    public function run() {
        $this->call([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
            AchievementsSeeder::class,
        ]);
    }
}
