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
        $user = User::create([
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'username' => 'superadmin',
            'name' => 'superadmin',
            'verified' => 1,
            'can_bbox'=> 1,
            'verification_required' => 0,
            'remaining_teams' => 10
        ]);
        $user->assignRole('superadmin');

        $user = User::create(['email' => 'admin@example.com',
            'password' => 'password',
            'username' => 'admin',
            'name' => 'admin',
            'verified' => 1,
            'can_bbox'=> 1,
            'verification_required' => 0,
            'remaining_teams' => 10
        ]);
        $user->assignRole('admin');

        $user = User::create(['email' => 'helper@example.com',
            'password' => 'password',
            'username' => 'helper',
            'name' => 'helper',
            'verified' => 1,
            'can_bbox'=> 1,
            'verification_required' => 0,
            'remaining_teams' => 1
        ]);
        $user->assignRole('helper');
    }
}
