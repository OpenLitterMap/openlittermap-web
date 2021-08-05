<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create(['email' => 'superadmin@example.com', 'password' => 'password', 'username' => 'superadmin123', 'name' => 'superadmin','verified' => 1, 'can_bbox'=> 1]);
        $user->assignRole('superadmin');

        $user = User::create(['email' => 'admin@example.com', 'password' => 'password', 'username' => 'admin123', 'name' => 'admin','verified' => 1, 'can_bbox'=> 1]);
        $user->assignRole('admin');

        $user = User::create(['email' => 'helper@example.com', 'password' => 'password', 'username' => 'helper123', 'name' => 'helper','verified' => 1, 'can_bbox'=> 1]);
        $user->assignRole('helper');
    }
}