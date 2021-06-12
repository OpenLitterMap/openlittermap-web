<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Country;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetTotalValuesOnCountries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:reset-total-on-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For each country, move all total values to redis';

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
        $countries = Country::select('id')->where('manual_verify', 1)->get();

        foreach ($countries as $country)
        {
            $query = Photo::where([
                ['verified', '>=', 2],
                'country_id' => $country->id
            ]);

             $total_photos = $query->count();
             $total_litter = $query->sum('total_litter');

             Redis::hincrby("country:$country->id", "total_photos", $total_photos);
             Redis::hincrby("country:$country->id", "total_litter", $total_litter);
        }
    }
}
