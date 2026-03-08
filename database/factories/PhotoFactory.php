<?php

namespace Database\Factories;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition()
    {
        $lat = $this->faker->latitude;
        $lon = $this->faker->longitude;

        return [
            'user_id' => User::factory(),
            'filename' => $this->faker->name . '.' . $this->faker->randomElement(['jpg', 'png', 'heic']),
            'model' => 'Unknown',
            'datetime' => $this->faker->dateTime,
            'lat' => $lat,
            'lon' => $lon,
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
            'geom' => DB::raw("ST_GeomFromText('POINT($lat $lon)', 4326)"),
        ];
    }
}
