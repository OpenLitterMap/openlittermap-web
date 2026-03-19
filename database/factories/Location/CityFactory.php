<?php

namespace Database\Factories\Location;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition()
    {
        return [
            'created_by' => User::factory(),
            'city' => $this->faker->city(),
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
        ];
    }
}
