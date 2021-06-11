<?php

namespace Database\Factories\Litter\Categories;

use App\Models\Litter\Categories\Political;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoliticalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Political::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'independent' => $this->faker->randomDigit
        ];
    }
}
