<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('team_types')->insert([
            'team' => 'Community',
            'price' => 0,
            'description' => 'A community of like-minded individuals',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
