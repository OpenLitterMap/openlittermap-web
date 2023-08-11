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
            ['country' => 'United States', 'shortcode' => 'US'],
            ['country' => 'Canada', 'shortcode' => 'CA'],
            ['country' => 'Brazil', 'shortcode' => 'BR'],
        ];

        DB::table('countries')->insert($countries);
    }
}
