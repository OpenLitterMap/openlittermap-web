<?php

namespace Database\Factories\Location;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = State::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'created_by' => User::factory()->create(),
            'state' => $this->faker->state,
            'country_id' => Country::factory()->create()
        ];
    }
}
