<?php

namespace App\Console\Commands\Photos;

use App\Http\Controllers\MapController;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTimeSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:photos:generate-dummy-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates Dummy photos in Ireland in Cork';

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
//        City::create([
//           'City' => "Cork",
//        ]);

        for($i=0;$i<1500;$i++) {
            $lat = rand(51.85391800*100000000, 51.92249800*100000000) / 100000000;
            $lon = rand(-8.53209200*100000000, -8.36823900*100000000) / 100000000;

            Photo::create([
                'total_litter' => 5,
                'user_id' => 1,
                'country_id' => 1,
                'city_id' => 1,
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
            User::create([
                'name' => 'Dummy'+$i,
                'email' => 'test@test.com',
                'password' => 'testing',
                'username' => 'test'
                ]);
        }
        (new \App\Http\Controllers\MapController)->getCountries();
    }
}
