<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Drugs;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrugsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Drugs::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'needles' => $this->faker->randomDigit
        ];
    }
}
