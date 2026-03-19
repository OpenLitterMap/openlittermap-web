<?php

namespace Database\Factories\Location;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StateFactory extends Factory
{
    protected $model = State::class;

    public function definition()
    {
        return [
            'created_by' => User::factory(),
            'state' => $this->faker->state(),
            'country_id' => Country::factory(),
        ];
    }
}
