<?php

namespace App\Console\Commands\Photos;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;

class GenerateDummyPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:photos:generate-dummy-photos {photos=1500}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates Dummy photos in Ireland in Cork,' .
    ' you can add an argument of how many photos to generate by typing in a number after' .
    ' olm:photos:generate-dummy-photos. ' .
    ' e.g. php artisan olm:photos:generate-dummy-photos 1500';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userId = User::whereEmail('admin@example.com')->first()->id;

        $ireland = Country::firstOrCreate([
            'country' => 'Ireland',
            'shortcode' => 'ie',
            'created_by' => $userId,
        ]);

        $cork = City::firstOrCreate([
            'city' => 'Cork',
            'country_id' => $ireland->id,
            'created_by' => $userId
        ]);

        State::firstOrCreate([
            'state' => 'County Cork',
            'country_id' => $ireland->id,
            'created_by' => $userId
        ]);

        $photosToGen = $this->argument('photos');

        $photos = $this->generatePhotos($photosToGen, $userId, $ireland, $cork);

        $this->insertPhotos($photosToGen, $photos);
    }

    /**
     * @param $photosToGen
     * @param $userId
     * @param $ireland
     * @param $cork
     * @return array
     */
    protected function generatePhotos($photosToGen, $userId, $ireland, $cork): array
    {
        $this->line('Generating photos...');

        $bar = $this->output->createProgressBar($photosToGen);
        $bar->setFormat('debug');
        $bar->start();

        $photos = [];
        for ($i = 0; $i < $photosToGen; $i++) {
            $lat = rand(51.85391800 * 100000000, 51.92249800 * 100000000) / 100000000;
            $lon = rand(-8.53209200 * 100000000, -8.36823900 * 100000000) / 100000000;

            $photos[] = [
                'total_litter' => 5,
                'user_id' => $userId,
                'country_id' => $ireland->id,
                'city_id' => $cork->id,
                'lat' => $lat,
                'lon' => $lon,
                'model' => "iPhone 5",
                'filename' => "dummy.png",
                'datetime' => "2021-06-04 16:27:37",
                'verified' => 2,
                'verification' => 1,
                'remaining' => 1,
                'geohash' => \GeoHash::encode($lat, $lon),
            ];

            $bar->advance();
        }

        $bar->finish();

        return $photos;
    }

    /**
     * @param $photosToGen
     * @param array $photos
     */
    protected function insertPhotos($photosToGen, array $photos): void
    {
        $this->newLine();
        $this->line('Inserting photos...');

        $bar = $this->output->createProgressBar(ceil($photosToGen / 1000));
        $bar->setFormat('debug');
        $bar->start();

        collect($photos)->chunk(1000)->each(function ($chunk) use ($bar) {
            Photo::insert($chunk->toArray());
            $bar->advance();
        });

        $bar->finish();

        $this->newLine();
        $this->line('Done!');
    }
}
