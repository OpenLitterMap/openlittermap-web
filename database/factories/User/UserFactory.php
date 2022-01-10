<?php

namespace Database\Factories\User;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
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
            'remaining_teams' => 1
        ];
    }

    /**
     * Indicate that the user is verified.
     *
     * @return Factory
     */
    public function verified()
    {
        return $this->state(function (array $attributes) {
            return [
                'verification_required' => false,
            ];
        });
    }
}
