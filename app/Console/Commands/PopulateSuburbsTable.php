<?php

namespace App\Console\Commands;

use App\Suburb;
use App\City;
use App\State;
use App\Country;
use App\Photo;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;

class PopulateSuburbsTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:populate-suburbs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate the suburbs table';

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
        $suburbs = Suburb::all();
        $photos = Photo::where('verified', '>', 0)->get();
        foreach($photos as $photo) {
            if($photo->suburb != "null") {
                // check if suburb exists 
                $suburb = $photo->suburb;
                if (!Redis::sismember('suburbs', $suburb)) {
                    Redis::sadd('suburbs', $suburb);
                        if(!array_key_exists($suburb, $suburbs)) {
                            $sub = new Suburb;
                            $sub->suburb = $suburb;
                            $sub->country_id = $photo->country_id;
                            $sub->state_id = $photo->state_id;
                            $sub->city_id = $photo->city_id;
                            $sub->save();
                        }
                }
            }
        }
    }
}
