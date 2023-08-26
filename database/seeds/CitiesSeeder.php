<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cities = [
            ['city' => 'Cork', 'country_id' => 1],
            ['city' => 'Galway', 'country_id' => 1],
            ['city' => 'Limerick', 'country_id' => 1],
            ['city' => 'New York', 'country_id' => 2],
            ['city' => 'Los Angeles', 'country_id' => 2],
            ['city' => 'Toronto', 'country_id' => 3],
            ['city' => 'Vancouver', 'country_id' => 3],
            ['city' => 'Montreal', 'country_id' => 3],
            ['city' => 'Calgary', 'country_id' => 3],
            ['city' => 'Edmonton', 'country_id' => 3],
            ['city' => 'Fortaleza', 'country_id' => 4],
            ['city' => 'Rio de Janeiro', 'country_id' => 4],
            ['city' => 'Bahia', 'country_id' => 4],
        ];

        DB::table('cities')->insert($cities);
    }
}
