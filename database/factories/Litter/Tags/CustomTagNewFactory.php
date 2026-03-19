<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\CustomTagNew;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomTagNewFactory extends Factory
{
    protected $table = CustomTagNew::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
        ];
    }
}
