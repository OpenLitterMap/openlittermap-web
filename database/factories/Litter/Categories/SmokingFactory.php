<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Smoking;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmokingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Smoking::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'butts' => $this->faker->randomDigit
        ];
    }
}
