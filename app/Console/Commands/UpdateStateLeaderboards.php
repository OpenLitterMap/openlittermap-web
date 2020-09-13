<?php

namespace App\Console\Commands;

use App\Models\User\User;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Location\Country;
use Illuminate\Support\Facades\Redis;

use Illuminate\Console\Command;

class UpdateStateLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-state-leaderboards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the leaderboards for each state';

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

        // $states = State::all();

        $users = User::where([
            ['has_uploaded', 1],
            ['show_name', 1],
        ])->orWhere([
            ['has_uploaded', 1],
            ['show_username', 1]
        ])->get();

        foreach($users as $user) {
            $photos = Photo::where('user_id', $user->id)->get();
            foreach($photos as $photo) {
                $country = Country::find($photo->country_id);
                $state = State::find($photo->state_id);
                Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
            }
        }
    }
}
