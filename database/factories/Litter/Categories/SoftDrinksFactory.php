<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\SoftDrinks;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoftDrinksFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SoftDrinks::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'waterBottle' => $this->faker->randomDigit
        ];
    }
}
