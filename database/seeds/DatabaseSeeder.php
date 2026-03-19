<?php

namespace Database\Seeders;

use Database\Seeders\Tags\CreateAllTagsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Populate the application's database
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

        $this->call(CreateAllTagsSeeder::class);
    }
}
