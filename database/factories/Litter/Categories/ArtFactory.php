<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Art;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArtFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Art::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'item' => $this->faker->randomDigit
        ];
    }
}
