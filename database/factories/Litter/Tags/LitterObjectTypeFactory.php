<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\LitterObjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LitterObjectTypeFactory extends Factory
{
    protected $model = LitterObjectType::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
            'name' => $this->faker->word,
        ];
    }
}
