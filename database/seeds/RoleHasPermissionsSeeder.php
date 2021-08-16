<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleHasPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('role_has_permissions')->insert([
           'permission_id' => 1,
           'role_id' => 1,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 2,
           'role_id' => 1,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 3,
           'role_id' => 1,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 4,
           'role_id' => 1,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 5,
           'role_id' => 1,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 1,
           'role_id' => 2,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 2,
           'role_id' => 2,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 3,
           'role_id' => 2,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 5,
           'role_id' => 2,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 1,
           'role_id' => 3,
        ]);
        DB::table('role_has_permissions')->insert([
           'permission_id' => 2,
           'role_id' => 3,
        ]);
    }
}
