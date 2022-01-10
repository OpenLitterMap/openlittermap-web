<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Photo::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
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
