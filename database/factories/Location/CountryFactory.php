<?php

namespace Database\Factories\Location;

use App\Models\Location\Country;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'created_by' => User::factory()->create(),
            'country' => $this->faker->country,
            'shortcode' => $this->faker->countryCode,
            'slug' => $this->faker->slug,
        ];
    }
}
