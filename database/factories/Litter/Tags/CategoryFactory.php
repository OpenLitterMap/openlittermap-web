<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
        ];
    }
}
