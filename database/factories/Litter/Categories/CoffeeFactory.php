<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Coffee;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoffeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Coffee::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'coffeeCups' => $this->faker->randomDigit
        ];
    }
}
