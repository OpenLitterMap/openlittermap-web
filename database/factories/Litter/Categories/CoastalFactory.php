<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Coastal;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoastalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Coastal::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'microplastics' => $this->faker->randomDigit
        ];
    }
}
