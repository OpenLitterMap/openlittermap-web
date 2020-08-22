<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Photo;
use App\User;
use App\Country;
use App\State;
use App\City;

class ResetLittercoinOwed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ltrx:resetowed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force LittercoinOwed and Paid back to 0.';

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
        $users = User::where('littercoin_owed', '!=', 0)->get();
        foreach($users as $user) {
            $user->littercoin_owed = 0;
            $user->save();
        }

        $countries = Country::where([
            ['littercoin_paid', 1],
        ])->get();
        $states = State::where([
            ['littercoin_paid', 1],
        ])->get();
        $cities = City::where([
            ['littercoin_paid', 1],
        ])->get();
        foreach($countries as $country) {
            $country->littercoin_paid = 0;
            $country->save();
        }
        foreach($states as $state) {
            $state->littercoin_paid = 0;
            $state->save();
        }
        foreach($cities as $city) {
            $city->littercoin_paid = 0;
            $city->save();
        }

    }
}
