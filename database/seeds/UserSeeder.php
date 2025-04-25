<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    protected array $socials = [
		"social_reddit" => "https://reddit.com/r/openlittermap",
		"social_twitter" => "https://twitter.com/openlittermap",
		"social_facebook" => "https://facebook.com/openlittermap",
		"social_linkedin" => "https://linkedin.com/company/openlittermap",
		"social_personal" => "https://openlittermap.com",
		"social_instagram" => "https://instagram.com/openlittermap"
    ];

    public function run (): void
    {
        $this->createAdmins();

        // Create one normal user with a known email address
        if (!User::where('email', 'normal@example.com')->exists()) {
            User::create([
                'email' => 'normal@example.com',
                'password' => 'password',
                'username' => 'normal',
                'name' => 'normal',
                'verified' => 1,
                'can_bbox'=> 1,
                'verification_required' => 0,
                'remaining_teams' => 1,
                'settings' => $this->socials,
                'show_name' => 1,
                'show_username' => 1
            ]);
        }

        // Create another 50 normal users
        User::factory()->count(50)->create([
            'settings' => $this->socials
        ]);
    }

    protected function createAdmins (): void
    {
        if (!User::where('email', 'superadmin@example.com')->exists())
        {
            $superAdmin = User::create([
                'email' => 'superadmin@example.com',
                'password' => 'password',
                'username' => 'superadmin',
                'name' => 'superadmin',
                'verified' => 1,
                'can_bbox'=> 1,
                'verification_required' => 0,
                'remaining_teams' => 10,
                'settings' => $this->socials,
                'show_name' => 1,
                'show_username' => 1
            ]);
            $superAdmin->assignRole('superadmin');
        }

        if (!User::where('email', 'admin@example.com')->exists())
        {
            $admin = User::create([
                'email' => 'admin@example.com',
                'password' => 'password',
                'username' => 'admin',
                'name' => 'admin',
                'verified' => 1,
                'can_bbox' => 1,
                'verification_required' => 0,
                'remaining_teams' => 10,
                'settings' => $this->socials,
                'show_name' => 1,
                'show_username' => 1
            ]);
            $admin->assignRole('admin');
        }

        if (!User::where('email', 'helper@example.com')->exists())
        {
            $helper = User::create([
                'email' => 'helper@example.com',
                'password' => 'password',
                'username' => 'helper',
                'name' => 'helper',
                'verified' => 1,
                'can_bbox'=> 1,
                'verification_required' => 0,
                'remaining_teams' => 1,
                'settings' => $this->socials,
                'show_name' => 1,
                'show_username' => 1
            ]);
            $helper->assignRole('helper');
        }
    }
}
