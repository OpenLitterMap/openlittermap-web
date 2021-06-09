<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('plans')->insert([
            'name' => 'Free',
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'subtitle' => '',
            'images' => 0,
            'verify' => 0,
            'product_id' => '',
            'plan_id' => '',
        ]);
    }
}
