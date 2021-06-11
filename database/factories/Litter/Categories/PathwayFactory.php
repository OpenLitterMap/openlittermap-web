<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Pathway;
use Illuminate\Database\Eloquent\Factories\Factory;

class PathwayFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Pathway::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'gutter' => $this->faker->randomDigit
        ];
    }
}
