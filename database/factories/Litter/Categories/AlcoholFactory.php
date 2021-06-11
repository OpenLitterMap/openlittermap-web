<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Alcohol;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlcoholFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Alcohol::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'beerCan' => $this->faker->randomDigit
        ];
    }
}
