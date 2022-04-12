<?php

namespace Database\Seeders; // With laravel 8+, seeders are now namespaced

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(PlanSeeder::class);
        $this->call(TeamTypeSeeder::class);
        $this->call(DonationAmountsSeeder::class);
        $this->call(LevelSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RoleHasPermissionsSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(TagSeeder::class);
    }
}
