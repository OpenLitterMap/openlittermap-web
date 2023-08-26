<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $countries = [
            ['country' => 'Ireland', 'shortcode' => 'ie'],
            ['country' => 'United States', 'shortcode' => 'is'],
            ['country' => 'Canada', 'shortcode' => 'ca'],
            ['country' => 'Brazil', 'shortcode' => 'br'],
        ];

        DB::table('countries')->insert($countries);
    }
}
