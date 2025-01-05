<?php

namespace Database\Seeders;

use Faker\Factory;
use App\Models\Photo;
use Illuminate\Database\Seeder;
use App\Models\Location\Country;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use Illuminate\Support\Facades\DB;

class PhotosSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create();

        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);

        for ($i = 0; $i < 1000; $i++)
        {
            // 1. Upload the image - can be skipped for now

            // 2. Get one of the countries
            $country = Country::where('id', $faker->numberBetween(1, 10))->first();

            // 3. Create the Photo with a random userId
            $userId = DB::table('users')->orderByRaw('RAND()')->first()->id;
            $photo = $this->createPhoto($faker, $country, $userId);

            // 4. Award XP & Update Leaderboards
            $updateLeaderboardsAction->run($photo, $userId);
        }
    }

    protected function createPhoto ($faker, Country $country, int $userId): Photo
    {
        $state = $country->states->random();
        $city = $state->cities->random();

        return Photo::create([
            'user_id' => $userId,
            'filename' => 'images/butts.png',
            'model' => "iPhone 12",
            'datetime' => $faker->dateTimeThisYear,
            'verified' => 2,
            'verification' => 1,
            'remaining' => $faker->boolean,
            'lat' => $faker->latitude,
            'lon' => $faker->longitude,
            'display_name' => $faker->address,
            'location' => $faker->word,
            'road' => $faker->streetName,
            'suburb' => $faker->citySuffix,

            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'country_code' => $country->shortcode,
            'country' => $country->country,
            'state_district' => $state->state,
            'city' => $city->city,
        ]);
    }
}
