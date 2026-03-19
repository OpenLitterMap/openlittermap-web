<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\Materials;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialsFactory extends Factory
{
    protected $model = Materials::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
        ];
    }
}
