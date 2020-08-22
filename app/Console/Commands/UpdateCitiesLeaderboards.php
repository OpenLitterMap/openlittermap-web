<?php

namespace App\Console\Commands;

use App\Photo;
use App\User;
use App\City;
use App\State;
use App\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class UpdateCitiesLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-cities-leaderboards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Leaderboards for each city.';

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
            ['show_name', 1]
        ])->get();

        foreach($users as $user) {
            $photos = Photo::where('user_id', $user->id)->get();
            foreach($photos as $photo) {
                $country = Country::find($photo->country_id);
                $state = State::find($photo->state_id);
                $city = City::find($photo->city_id);
                Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
            }
        }
    }
}
