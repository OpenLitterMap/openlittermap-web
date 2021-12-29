<?php

namespace Database\Factories\Teams;

use App\Models\Teams\TeamType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TeamType::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'team' => $this->faker->name,
            'price' => $this->faker->randomDigit,
            'description' => $this->faker->word
        ];
    }
}
