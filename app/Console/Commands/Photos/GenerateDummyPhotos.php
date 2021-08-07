<?php

namespace App\Console\Commands\Photos;

use App\Helpers\Get\LocationHelper;
use App\Http\Controllers\MapController;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTimeSeries extends Command
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
    protected $description = 'Generates Dummy photos in Ireland in Cork, you can add an argument of how many photos to generate by typing in a number after olm:photos:generate-dummy-photos.   e.g. php artisan olm:photos:generate-dummy-photos 1500';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle ()
    {
        if(!(User::where('email', 'test@test.com')->first())) {
            User::firstOrNew([
                'name' => 'DummyUser',
                'email' => 'test@test.com',
                'password' => 'testing',
                'username' => 'test',
                'verified' => 1
            ])->save();
        }

        $dummy_id = User::where('email', 'test@test.com')->first()->id;
        Country::firstOrCreate([
            'country' => 'Ireland',
            'shortcode' => 'ie',
            'created_by' => $dummy_id,
        ]);

        $ireland_id = Country::where('country', 'Ireland')->first()->id;
        City::firstOrCreate([
            'city' => 'Cork',
            'country_id' => $ireland_id,
            'created_by' => $dummy_id
        ]);

        State::firstOrCreate([
            'state' => 'County Cork',
            'country_id' => $ireland_id,
            'created_by' => $dummy_id
        ]);

        $cork_id = City::where('city', 'Cork')->first()->id;


        $photos_to_gen = $this->argument('photos');

        for($i=0;$i<$photos_to_gen;$i++) {
            $lat = rand(51.85391800*100000000, 51.92249800*100000000) / 100000000;
            $lon = rand(-8.53209200*100000000, -8.36823900*100000000) / 100000000;

            Photo::create([
                'total_litter' => 5,
                'user_id' => $dummy_id,
                'country_id' => $ireland_id,
                'city_id' => $cork_id,
                'lat' => $lat,
                'lon' => $lon,
                'model' => "iPhone 5",
                'filename' => "dummy.png",
                'datetime' => "2021-06-04 16:27:37",
                'verified' => 2,
                'verification' => 1,
                'remaining' => 1,
                'geohash' => \GeoHash::encode($lat, $lon),
            ]);
        }
    }
}
