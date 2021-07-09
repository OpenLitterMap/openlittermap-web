<?php

namespace Database\Factories;

use App\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

class LevelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Level::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'xp' => $this->faker->randomElement([10, 50, 100, 1000, 10000, 100000]),
            'level' => $this->faker->randomDigit
        ];
    }
}
