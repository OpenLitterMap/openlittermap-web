<?php

namespace App\Console\Commands\Photos;

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
    protected $signature = 'olm:photos:regenerate-time-series';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate entire photos_per_month time series string for all locations (Country, State & City)';

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
        $months = [0, '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

        $countries = Country::where('manual_verify', 1)->get();

        foreach ($countries as $country)
        {
            echo "Country " . $country->country . " \n";
            $photosPerMonth = [];

            $photos = Photo::select('id', 'datetime', 'verified', 'country_id')
                ->where([
                    'country_id' => $country->id,
                    'verified' => 2
                ])
                ->orderBy('datetime', 'asc')
                ->get();

            $photos = $photos->groupBy(function($val) {
                return Carbon::parse($val->datetime)->format('m-y');
            });

            foreach ($photos as $index => $monthlyPhotos)
            {
                $month = $months[(int)$substr = substr($index,0,2)];
                $year = substr($index,2,5);
                $photosPerMonth[$month.$year] = $monthlyPhotos->count(); // Mar-17
                // $total_photos += $monthlyPhotos->count();
            }

            $country->photos_per_month = json_encode($photosPerMonth);
            $country->save();
        }

        $states = State::where('manual_verify', 1)->get();

        foreach ($states as $state)
        {
            echo "State " . $state->state . " \n";
            $photosPerMonth = [];

            $photos = Photo::select('id', 'datetime', 'verified', 'country_id')
                ->where([
                    'state_id' => $state->id,
                    'verified' => 2
                ])
                ->orderBy('datetime', 'asc')
                ->get();

            $photos = $photos->groupBy(function($val) {
                return Carbon::parse($val->datetime)->format('m-y');
            });

            foreach ($photos as $index => $monthlyPhotos)
            {
                $month = $months[(int)$substr = substr($index,0,2)];
                $year = substr($index,2,5);
                $photosPerMonth[$month.$year] = $monthlyPhotos->count(); // Mar-17
                // $total_photos += $monthlyPhotos->count();
            }

            $state->photos_per_month = json_encode($photosPerMonth);
            $state->save();
        }

        $cities = City::where('manual_verify', 1)->get();

        foreach ($cities as $city)
        {
            echo "City " . $city->city . " \n";
            $photosPerMonth = [];

            $photos = Photo::select('id', 'datetime', 'verified', 'country_id')
                ->where([
                    'city_id' => $city->id,
                    'verified' => 2
                ])
                ->orderBy('datetime', 'asc')
                ->get();

            $photos = $photos->groupBy(function($val) {
                return Carbon::parse($val->datetime)->format('m-y');
            });

            foreach ($photos as $index => $monthlyPhotos)
            {
                $month = $months[(int)$substr = substr($index,0,2)];
                $year = substr($index,2,5);
                $photosPerMonth[$month.$year] = $monthlyPhotos->count(); // Mar-17
                // $total_photos += $monthlyPhotos->count();
            }

            $city->photos_per_month = json_encode($photosPerMonth);
            $city->save();
        }
    }
}
