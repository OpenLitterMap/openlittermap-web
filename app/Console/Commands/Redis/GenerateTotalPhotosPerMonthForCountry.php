<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateTotalPhotosPerMonthForCountry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:GenerateTotalPhotosPerMonthForCountry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all of the monthly total values for each location';

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
     */
    public function handle()
    {
        $this->processCountries();
        $this->processStates();
        $this->processCities();
    }

    /**
     *
     */
    public function processCountries ()
    {
        $countries = Country::where('manual_verify', true)->get();

        foreach ($countries as $country)
        {
            echo "Country: $country->country \n";

            $photo = Photo::where('country_id', $country->id)->orderBy('id')->first();

            if (!$photo) continue;

            $start = Carbon::parse($photo->created_at)->startOfMonth();

            $end = now()->startOfMonth();

            $currentMonth = $start->copy();

            $total = 0;

            while ($currentMonth->lte($end))
            {
                // format month eg. 10-15
                $formattedMonth = $currentMonth->format('m-y');

                // Check if Redis has data for the month
                $count = (int)Redis::hget("ppm:country:$country->id", $formattedMonth);

                // Add this to the total
                $total += $count;

                // Add the total to Redis for this month
                Redis::hincrby("totalppm:country:$country->id", $formattedMonth, $total);

                $currentMonth->addMonth();
            }
        }
    }

    /**
     *
     */
    public function processStates ()
    {
        $states = State::all();

        foreach ($states as $state)
        {
            echo "State: $state->state \n";

            $photo = Photo::where('state_id', $state->id)->orderBy('id')->first();

            if (!$photo) continue;

            $start = Carbon::parse($photo->created_at)->startOfMonth();

            $end = now()->startOfMonth();

            $currentMonth = $start->copy();

            $total = 0;

            while ($currentMonth->lte($end))
            {
                // format month eg. 10-15
                $formattedMonth = $currentMonth->format('m-y');

                // Check if Redis has data for the month
                $count = (int)Redis::hget("ppm:state:$state->id", $formattedMonth);

                // Add this to the total
                $total += $count;

                // Add the total to Redis for this month
                Redis::hincrby("totalppm:state:$state->id", $formattedMonth, $total);

                $currentMonth->addMonth();
            }
        }
    }

    /**
     *
     */
    public function processCities ()
    {
        $cities = City::all();

        foreach ($cities as $city)
        {
            echo "City: $city->city \n";

            $photo = Photo::where('city_id', $city->id)->orderBy('id')->first();

            if (!$photo) continue;

            $start = Carbon::parse($photo->created_at)->startOfMonth();

            $end = now()->startOfMonth();

            $currentMonth = $start->copy();

            $total = 0;

            while ($currentMonth->lte($end))
            {
                // format month eg. 10-15
                $formattedMonth = $currentMonth->format('m-y');

                // Check if Redis has data for the month
                $count = (int)Redis::hget("ppm:city:$city->id", $formattedMonth);

                // Add this to the total
                $total += $count;

                // Add the total to Redis for this month
                Redis::hincrby("totalppm:city:$city->id", $formattedMonth, $total);

                $currentMonth->addMonth();
            }
        }
    }
}
