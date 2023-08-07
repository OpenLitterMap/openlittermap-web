<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Country;
use App\Models\Location\State;
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
    protected $description = 'Command description';

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
        $countries = Country::all();

        foreach ($countries as $country)
        {
            $total = 0;

            foreach ($country->ppm as $key => $value)
            {
                $valueNumber = json_decode($value);

                $total += $valueNumber;

                Redis::hincrby("totalppm:country:$country->id", $key, $total);
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
            $total = 0;

            foreach ($state->ppm as $key => $value)
            {
                $valueNumber = json_decode($value);

                $total += $valueNumber;

                Redis::hincrby("totalppm:state:$state->id", $key, $total);
            }
        }
    }

    /**
     *
     */
    public function processCities ()
    {
        $cities = State::all();

        foreach ($cities as $city)
        {
            $total = 0;

            foreach ($city->ppm as $key => $value)
            {
                $valueNumber = json_decode($value);

                $total += $valueNumber;

                Redis::hincrby("totalppm:city:$city->id", $key, $total);
            }
        }
    }


}
