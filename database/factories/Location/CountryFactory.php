<?php

namespace Database\Factories\Location;

use App\Models\Location\Country;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition()
    {
        return [
            'created_by' => User::factory(),
            'country' => $this->faker->country(),
            'shortcode' => $this->faker->unique()->countryCode(),
        ];
    }
}
