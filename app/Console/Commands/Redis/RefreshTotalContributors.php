<?php

namespace App\Console\Commands\Redis;

use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RefreshTotalContributors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:refresh-total-contributors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate total contributors per city and move data to redis';

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
     * Select the location_ids a user has uploaded data to
     *
     * country_id, state_id, city_id
     *
     * And add the user_id to a redis set if it does not exist
     *
     * country:id:user_ids
     * state:id:user_ids
     * city:id_user_ids
     *
     * Note: The user_id will not be duplicated. It will only appear in the set once.
     */
    public function handle ()
    {
        $users = User::where('has_uploaded', 1)->get();

        foreach ($users as $user)
        {
            $countryPhotos = $user->photos()->select('country_id')->distinct()->get();
            foreach ($countryPhotos as $photo)
            {
                Redis::sadd("country:$photo->country_id:user_ids", $user->id);
            }

            $statePhotos = $user->photos()->select('state_id')->distinct()->get();
            foreach ($statePhotos as $photo)
            {
                Redis::sadd("state:$photo->state_id:user_ids", $user->id);
            }

            $cityPhotos = $user->photos()->select('city_id')->distinct()->get();
            foreach ($cityPhotos as $photo)
            {
                Redis::sadd("city:$photo->city_id:user_ids", $user->id);
            }
        }
    }
}
