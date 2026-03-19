<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\BrandList;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandListFactory extends Factory
{
    protected $model = BrandList::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
        ];
    }
}
