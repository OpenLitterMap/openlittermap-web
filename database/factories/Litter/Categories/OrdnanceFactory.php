<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Ordnance;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdnanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ordnance::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'land_mine' => $this->faker->randomDigit,
            'missile' => $this->faker->randomDigit,
            'grenade' => $this->faker->randomDigit,
            'shell' => $this->faker->randomDigit,
            'other' => $this->faker->randomDigit,
        ];
    }
}
