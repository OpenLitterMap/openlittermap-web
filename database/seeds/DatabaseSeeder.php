<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run (): void
    {
        // Before creating users & photos
        $this->call(PlanSeeder::class);
        $this->call(TeamTypeSeeder::class);
        $this->call(DonationAmountsSeeder::class);
        $this->call(LevelSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RoleHasPermissionsSeeder::class);

        // Create Locations
        $this->call(LocationsSeeder::class);

        // Create Users & Photos & reward XP
        $this->call(UserSeeder::class);
        $this->call(PhotosSeeder::class);

        // Populate photos with Tags v2
        $this->call(CategoryLitterObjectSeeder::class);
    }
}
