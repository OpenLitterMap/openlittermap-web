<?php

namespace App\Console\Commands\Photos;

use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use Illuminate\Console\Command;

class GenerateData extends Command
{
    protected $signature = 'olm:generate-data {photos=1500}';

    protected $description = 'Generates data in Cork, Ireland. Default = 1500 photos';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle (): void
    {
        $userId = User::first()->id;

        $country = Country::firstOrCreate([
            'country' => 'Ireland',
            'shortcode' => 'ie',
            'created_by' => $userId,
        ]);

        State::firstOrCreate([
            'state' => 'County Cork',
            'country_id' => $country->id,
            'created_by' => $userId
        ]);

        $city = City::firstOrCreate([
            'city' => 'Cork',
            'country_id' => $country->id,
            'created_by' => $userId
        ]);

        $photosToGen = $this->argument('photos');

        $this->generatePhotos($photosToGen, $country, $city);
    }

    protected function generatePhotos ($photosToGen, $country, $city): array
    {
        $this->line('Generating photos...');

        $bar = $this->output->createProgressBar($photosToGen);
        $bar->setFormat('debug');
        $bar->start();

        $photos = [];

        // Get max 10 users
        $users = User::inRandomOrder()->limit(10)->get();

        for ($i = 0; $i < $photosToGen; $i++)
        {
            $lat = rand(51.85391800 * 100000000, 51.92249800 * 100000000) / 100000000;
            $lon = rand(-8.53209200 * 100000000, -8.36823900 * 100000000) / 100000000;

            // Pick 1 random user
            $user = $users->random();

            $createdAt = now()->subWeek()->startOfWeek()->addHour();

            $photo = Photo::create([
                'user_id' => $user->id,
                'country_id' => $country->id,
                'city_id' => $city->id,
                'lat' => $lat,
                'lon' => $lon,
                'model' => "iPhone 5",
                'filename' => "dummy.png",
                'datetime' => $createdAt,
                'verified' => 2,
                'verification' => 1,
                'remaining' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // TODO: Use v5 AddTagsToPhotoAction from Tags namespace to add tags
            $photo->save();

            $bar->advance();
        }

        $bar->finish();

        return $photos;
    }

}
