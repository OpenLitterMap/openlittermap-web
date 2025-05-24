<?php

namespace Database\Seeders\Tags;

use Database\Seeders\Achievements\GenerateAchievementsSeeder;
use Illuminate\Database\Seeder;

class CreateAllTagsSeeder extends Seeder
{
    public function run() {
        $this->call([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
            GenerateAchievementsSeeder::class,
        ]);
    }
}
