<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\LitterObject;
use App\Models\Photo;
use Illuminate\Database\Seeder;

class LitterTypeObjectSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        foreach ($categories as $category) {

            $litterObjects = $category->litterObjects;

        }
    }
}
