<?php

namespace App\Console\Commands\Photos;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateData extends Command
{
    protected $signature = 'olm:generate-data {photos=1500}';

    protected $description = 'Generates data in Cork, Ireland. Default = 1500 photos';

    private AddTagsToPhotoAction $addTagsToPhotoAction;

    public function __construct (AddTagsToPhotoAction $addTagsToPhotoAction)
    {
        parent::__construct();
        $this->addTagsToPhotoAction = $addTagsToPhotoAction;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userId = User::whereEmail('admin@example.com')->first()->id;

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

        $this->generatePhotos($photosToGen, $userId, $country, $city);
    }

    /**
     * @param $photosToGen
     * @param $userId
     * @param $ireland
     * @param $cork
     * @return array
     */
    protected function generatePhotos ($photosToGen, $userId, $ireland, $cork): array
    {
        $this->line('Generating photos...');

        $bar = $this->output->createProgressBar($photosToGen);
        $bar->setFormat('debug');
        $bar->start();

        $litterJson = $this->getLitterJson();
        $categories = ['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'sanitary', 'other'];

        $photos = [];
        for ($i = 0; $i < $photosToGen; $i++) {
            $lat = rand(51.85391800 * 100000000, 51.92249800 * 100000000) / 100000000;
            $lon = rand(-8.53209200 * 100000000, -8.36823900 * 100000000) / 100000000;

            $tags = [];

            foreach ($categories as $category) {
                if (isset($litterJson[$category])) {
                    $litterTypes = array_keys($litterJson[$category]);

                    // Ensure that we only select as many items as are available
                    $availableItems = count($litterTypes);
                    $numberToSelect = min(rand(1, 5), $availableItems);

                    // Select 1-5 random types of litter from the category
                    $selectedLitter = collect($litterTypes)->random($numberToSelect);

                    // Populate tags array with randomly selected litter types and quantities
                    foreach ($selectedLitter as $litterKey) {
                        $tags[$category][$litterKey] = rand(1, 5);
                    }
                }
            }

            $photo = Photo::create([
                'user_id' => $userId,
                'country_id' => $ireland->id,
                'city_id' => $cork->id,
                'lat' => $lat,
                'lon' => $lon,
                'model' => "iPhone 5",
                'filename' => "dummy.png",
                'datetime' => now()->subWeek()->startOfWeek()->addHour(),
                'verified' => 2,
                'verification' => 1,
                'remaining' => 1,
                'geohash' => \GeoHash::encode($lat, $lon),
                'created_at' => now()->subWeek()->startOfWeek()->addHour(),
                'updated_at' => now()->subWeek()->startOfWeek()->addHour(),
            ]);

            $this->addTagsToPhotoAction->run($photo, $tags);

            $photo->save();

            $bar->advance();
        }

        $bar->finish();

        return $photos;
    }

    protected function getLitterJson (): array
    {
        $path = resource_path('js/langs/en/litter.json');

        $contents = File::get($path);

        return json_decode($contents, true);
    }
}
