<?php

namespace App\Console\Commands\tmp;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateDailyLeaderboards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:generate-daily-leaderboards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Loop over all data and create daily leaderboards';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle ()
    {
        $photos = Photo::query()
            ->select(
                'id',
                'datetime',
                'user_id',
                'country_id',
                'state_id',
                'city_id',
                'total_litter'
            )
            ->where('verified', '>=', 2)
            ->orderBy('id', 'desc');

        $total = $photos->count();

        foreach ($photos->cursor() as $photo)
        {
            $user = User::find($photo->user_id);
            $userId = $user->id;
            $incrXp = $photo->total_litter;

            if ($incrXp === 0) {
                $incrXp = 1;
            }

            $datetime = Carbon::parse($photo->datetime);

            $year = $datetime->year;
            $month = $datetime->month;
            $day = $datetime->day;

            $country = Country::find($photo->country_id);
            $state = State::find($photo->state_id);
            $city = City::find($photo->city_id);

            if ($user)
            {
                Redis::zincrby("leaderboard:users:$year:$month:$day", $incrXp, $userId);
                Redis::zincrby("leaderboard:users:$year:$month", $incrXp, $userId);
                Redis::zincrby("leaderboard:users:$year", $incrXp, $userId);
            }

            if ($country)
            {
                Redis::zincrby("leaderboard:country:$photo->country_id:$year:$month:$day", $incrXp, $userId);
                Redis::zincrby("leaderboard:country:$photo->country_id:$year:$month", $incrXp, $userId);
                Redis::zincrby("leaderboard:country:$photo->country_id:$year", $incrXp, $userId);
            }

            if ($state)
            {
                Redis::zincrby("leaderboard:state:$photo->state_id:$year:$month:$day", $incrXp, $userId);
                Redis::zincrby("leaderboard:state:$photo->state_id:$year:$month", $incrXp, $userId);
                Redis::zincrby("leaderboard:state:$photo->state_id:$year", $incrXp, $userId);
            }

            if ($city)
            {
                Redis::zincrby("leaderboard:city:$photo->city_id:$year:$month:$day", $incrXp, $userId);
                Redis::zincrby("leaderboard:city:$photo->city_id:$year:$month", $incrXp, $userId);
                Redis::zincrby("leaderboard:city:$photo->city_id:$year", $incrXp, $userId);
            }

            $completed = ($photo->id / $total);

            $this->info($completed . " %");
        }
    }
}
