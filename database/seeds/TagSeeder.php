<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $ordnance = Category::factory()->create(['name' => 'Ordnance']);
        $militaryEquipment = Category::factory()->create(['name' => 'Military equipment or weaponry']);
        $militaryPersonnel = Category::factory()->create(['name' => 'Military personnel']);

        Tag::factory()->createMany([
            ['category_id' => $ordnance->id, 'name' => 'Small arms'],
            ['category_id' => $ordnance->id, 'name' => 'Land mine'],
            ['category_id' => $ordnance->id, 'name' => 'Missile'],
            ['category_id' => $ordnance->id, 'name' => 'Grenade'],
            ['category_id' => $ordnance->id, 'name' => 'Shell or projectile'],
            ['category_id' => $ordnance->id, 'name' => 'Other explosive hazards or its parts'],
        ]);

        Tag::factory()->createMany([
            ['category_id' => $militaryEquipment->id, 'name' => 'Armoured vehicle or its parts'],
            ['category_id' => $militaryEquipment->id, 'name' => 'Light weaponry or its parts'],
        ]);

        Tag::factory()->createMany([
            ['category_id' => $militaryPersonnel->id, 'name' => 'Suspected humane remains'],
            ['category_id' => $militaryPersonnel->id, 'name' => 'Personal items'],
            ['category_id' => $militaryPersonnel->id, 'name' => 'Documents'],
        ]);
    }
}
