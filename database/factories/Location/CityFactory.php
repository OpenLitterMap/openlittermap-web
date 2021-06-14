<?php

namespace Database\Factories\Location;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = City::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'created_by' => User::factory()->create(),
            'city' => $this->faker->city,
            'country_id' => Country::factory()->create(),
            'state_id' => State::factory()->create()
        ];
    }
}
