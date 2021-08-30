<?php

namespace Database\Factories\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'identifier' => $this->faker->unique()->lexify(),
            'created_by' => User::factory()
        ];
    }
}
