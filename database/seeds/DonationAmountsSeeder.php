<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DonationAmountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('donates')->insert([
            'amount' => 500,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
