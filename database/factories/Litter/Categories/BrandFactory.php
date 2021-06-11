<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'walkers' => $this->faker->randomDigit,
            'amazon' => $this->faker->randomDigit,
        ];
    }
}
