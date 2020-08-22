<?php

namespace App\Console\Commands;

use App\Photo;
use App\City;
use App\State;
use App\Events\NewStateAdded;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:generate-states';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate necessary states from existing photos';

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
        $photos = Photo::all();
        foreach($photos as $photo) {
            $state = $photo->county;
            $country = $photo->country;
            $city = $photo->city;
            if(!Redis::sismember('states', $state)) {
                Redis::sadd('states', $state);
                event(new NewStateAdded($state, $country));
            }
            $a = State::where('state', $state)->first();
            $stateID = $a->id;
            $photo->state_id = $stateID;
            $photo->save();
            $theCity = City::where('city', $city)->first();
            $theCity->state_id = $stateID;
            $theCity->save();
        }
    }
}
