<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\User;
use App\Photo;
use App\Country;
use App\State;
use App\City;
use Illuminate\Support\Facades\Redis;

//  DOES NOT WORK
//  DOES NOT WORK
//  DOES NOT WORK
//  DOES NOT WORK
//  DOES NOT WORK

class UpdateLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-leaderboards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all leaderboards';

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
        
        $users = User::where([
            ['has_uploaded', 1],
            ['show_name', 1],
        ])->orWhere([
            ['has_uploaded', 1],
            ['show_username', 1]
        ])->get();

        foreach($users as $user) {
            if (($user->show_name == 1) || ($user->show_username == 1)) {

                $country = Country::find($photo->country_id);
                $state = State::find($photo->state_id);
                $city = City::find($photo->city_id);

                Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
                Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
                Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
            }
        }




    }
}
