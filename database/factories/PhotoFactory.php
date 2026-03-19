<?php

namespace Database\Factories;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'filename' => $this->faker->name . '.' . $this->faker->randomElement(['jpg', 'png', 'heic']),
            'model' => 'Unknown',
            'datetime' => $this->faker->dateTime,
            'lat' => $this->faker->latitude,
            'lon' => $this->faker->longitude,
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
            // geom is auto-synced from lat/lon by MySQL trigger (photos_bi_geom)
        ];
    }
}
