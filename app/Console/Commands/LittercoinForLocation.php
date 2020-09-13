<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use App\Models\Photo;
use App\Models\User\User;

class LittercoinForLocation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ltrx:forlocation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Littercoin owed to Users for creating a Location';

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

        $countries = Country::where([
            ['manual_verify', 1],
            ['littercoin_paid', 0],
            ['id', '!=', 16] // error_country
        ])->get();
        $states = State::where([
            ['manual_verify', 1],
            ['littercoin_paid', 0],
            ['id', '!=', 46] // error_states
        ])->get();
        $cities = City::where([
            ['total_images', '>', 0],
            ['littercoin_paid', 0],
            ['id', '!=', 89]
        ])->get();

        foreach($countries as $country) {
            $user = User::find($country->photos()->first()->user_id);
            $user->littercoin_owed += 100;
            $user->save();
            $country->littercoin_paid = true;
            $country->save();
        }

        foreach($states as $state) {
            $user = User::find($state->photos()->first()->user_id);
            $user->littercoin_owed += 50;
            $user->save();
            $state->littercoin_paid = true;
            $state->save();
        }

        foreach($cities as $city) {
            $user = User::find($city->photos()->first()->user_id);
            $user->littercoin_owed += 25;
            $user->save();
            $city->littercoin_paid = true;
            $city->save();
        }
    }
}
