<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('permissions')->insert([
            'name' => 'update tags',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('permissions')->insert([
            'name' => 'create boxes',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('permissions')->insert([
            'name' => 'update boxes',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('permissions')->insert([
            'name' => 'view horizon',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('permissions')->insert([
            'name' => 'verify boxes',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
