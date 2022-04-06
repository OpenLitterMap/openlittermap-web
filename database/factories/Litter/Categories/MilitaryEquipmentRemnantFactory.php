<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\MilitaryEquipmentRemnant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MilitaryEquipmentRemnantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MilitaryEquipmentRemnant::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'weapon' => $this->faker->randomDigit,
        ];
    }
}
