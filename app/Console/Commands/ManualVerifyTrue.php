<?php

namespace App\Console\Commands;

use App\Country;
use App\State;
use App\City;
use Illuminate\Console\Command;

class ManualVerifyTrue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:statecity-true';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force all State and City manual verify to true';

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
    public function handle()
    {   

        $countries = Country::all();
        foreach($countries as $country) {
            $country->manual_verify = 1;
            $country->save();
        }

        $states = State::all();
        foreach($states as $state) {
            $state->manual_verify = 1;
            $state->save();
        }

        $cities = City::all();
        foreach($cities as $city) {
            $city->manual_verify = 1;
            $city->save();
        }
    }
}
