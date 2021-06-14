<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Sanitary;
use Illuminate\Database\Eloquent\Factories\Factory;

class SanitaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sanitary::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'tooth_pick' => $this->faker->randomDigit
        ];
    }
}
