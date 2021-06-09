<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

class FoodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Food::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'sweetWrappers' => $this->faker->randomDigit
        ];
    }
}
