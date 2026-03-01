<?php

namespace Database\Factories\Users;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        $name = $this->faker->name;

        return [
            'name' => $name,
            'username' => Str::slug($name),
            'email' => $this->faker->unique()->safeEmail,
            'verified' => true,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'images_remaining' => 1000,
            'remaining_teams' => 10,
            'show_name' => 1,
            'show_username' => 1,
            'show_name_maps' => 1,
            'show_username_maps' => 1,
            'show_name_createdby' => 1,
            'picked_up' => true,
        ];
    }

    public function verified()
    {
        return $this->state(function (array $attributes) {
            return [
                'verification_required' => false,
            ];
        });
    }
}
