<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('levels')->insert([
            'xp' => 10,
            'level' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('levels')->insert([
            'xp' => 50,
            'level' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('levels')->insert([
            'xp' => 100,
            'level' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('levels')->insert([
            'xp' => 500,
            'level' => 4,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('levels')->insert([
            'xp' => 2000,
            'level' => 5,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
