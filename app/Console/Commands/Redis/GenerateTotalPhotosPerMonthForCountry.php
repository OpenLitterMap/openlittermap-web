<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateTotalPhotosPerMonthForCountry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:GenerateTotalPhotosPerMonthForCountry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     */
    public function handle()
    {
        $countries = Country::all();

        foreach ($countries as $country)
        {
            $total = 0;

            foreach ($country->ppm as $key => $value)
            {
                $valueNumber = json_decode($value);

                $total += $valueNumber;

                Redis::hincrby("totalppm:country:$country->id", $key, $total);
            }
        }
    }
}
