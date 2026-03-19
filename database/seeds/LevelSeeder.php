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
        $levels = [
            ['id' => 1, 'xp' => 10, 'level' => 1],
            ['id' => 2, 'xp' => 20, 'level' => 2],
            ['id' => 3, 'xp' => 40, 'level' => 3],
            ['id' => 4, 'xp' => 60, 'level' => 4],
            ['id' => 5, 'xp' => 80, 'level' => 5],
            ['id' => 6, 'xp' => 100, 'level' => 6],
            ['id' => 7, 'xp' => 200, 'level' => 7],
            ['id' => 8, 'xp' => 300, 'level' => 8],
            ['id' => 9, 'xp' => 400, 'level' => 9],
            ['id' => 10, 'xp' => 500, 'level' => 10],
            ['id' => 11, 'xp' => 1000, 'level' => 11],
            ['id' => 12, 'xp' => 1500, 'level' => 12],
            ['id' => 13, 'xp' => 2000, 'level' => 13],
            ['id' => 14, 'xp' => 2500, 'level' => 14],
            ['id' => 15, 'xp' => 3000, 'level' => 15],
            ['id' => 16, 'xp' => 3500, 'level' => 16],
            ['id' => 17, 'xp' => 4000, 'level' => 17],
            ['id' => 18, 'xp' => 5000, 'level' => 18],
            ['id' => 19, 'xp' => 7500, 'level' => 19],
            ['id' => 20, 'xp' => 10000, 'level' => 20],
            ['id' => 21, 'xp' => 15000, 'level' => 21],
            ['id' => 22, 'xp' => 20000, 'level' => 22],
            ['id' => 23, 'xp' => 50000, 'level' => 23],
            ['id' => 24, 'xp' => 100000, 'level' => 24],
            ['id' => 25, 'xp' => 250000, 'level' => 25],
            ['id' => 26, 'xp' => 500000, 'level' => 26],
        ];

        foreach ($levels as $level) {
            DB::table('levels')->updateOrInsert(
                ['id' => $level['id']],
                ['xp' => $level['xp'], 'level' => $level['level']]
            );
        }
    }
}
