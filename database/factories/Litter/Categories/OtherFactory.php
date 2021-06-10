<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Other;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Other::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'random_litter' => $this->faker->randomDigit
        ];
    }
}
