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

class GeneratePhotosPerMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'time-series:generate-photos-per-month';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the photos per month for each User and Location';

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
     * @return int
     */
    public function handle()
    {
        $photos = Photo::query()->select(
            'id',
            'datetime',
            'user_id',
            'country_id',
            'state_id',
            'city_id'
        )
        ->where('verified', '>=', 2);

        $total = $photos->count();

        foreach ($photos->cursor() as $photo)
        {
            $date = Carbon::parse($photo->datetime)->format('m-y');

            $user = User::find($photo->user_id);

            $country = Country::find($photo->country_id);
            $state = State::find($photo->state_id);
            $city = City::find($photo->city_id);

            if ($user)
            {
                Redis::hincrby("ppm:user:$user->id", $date, 1);
            }

            if ($country)
            {
                Redis::hincrby("ppm:country:$country->id", $date, 1);
            }

            if ($state)
            {
                Redis::hincrby("ppm:state:$state->id", $date, 1);
            }

            if ($city)
            {
                Redis::hincrby("ppm:city:$city->id", $date, 1);
            }

            $completed = ($photo->id / $total);

            $this->info($completed . " %");
        }
    }
}
