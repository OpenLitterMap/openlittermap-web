<?php

namespace Database\Seeders;

use App\Models\Photo;
use Faker\Factory;
use Illuminate\Database\Seeder;

class PhotosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Factory::create();

        for ($i = 0; $i < 10; $i++) {
            Photo::create([
                'user_id' => $faker->numberBetween(1, 4),
                //'filename' => $faker->image(public_path('assets/bird-plastic.jpg'), 400, 300, null, false),
                'filename' => $faker->word . '-' . $faker->randomNumber() . '.jpg',
                'model' => $faker->word,
                'datetime' => $faker->dateTimeThisYear,
                'verified' => $faker->boolean,
                'verification' => $faker->randomFloat(2, 0, 100),
                'remaining' => $faker->boolean,
                'lat' => $faker->latitude,
                'lon' => $faker->longitude,
                'display_name' => $faker->address,
                'location' => $faker->word,
                'road' => $faker->streetName,
                'suburb' => $faker->citySuffix,
                'city' => $faker->city,
                //'county' => $faker->county,
                'state_district' => $faker->state,
                'country' => $faker->country,
                'country_code' => $faker->countryCode,
            ]);
        }
    }
}
