<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->create(),
            'filename' => $this->faker->name . $this->faker->fileExtension,
            'model' => 'Unknown',
            'datetime' => $this->faker->dateTime,
            'lat' => $this->faker->latitude,
            'lon' => $this->faker->longitude
        ];
    }
}
