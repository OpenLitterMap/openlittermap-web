<?php

namespace Database\Factories\Litter\Tags;

use App\Models\Litter\Tags\LitterObject;
use Illuminate\Database\Eloquent\Factories\Factory;

class LitterObjectFactory extends Factory
{
    protected $table = LitterObject::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word,
        ];
    }
}
