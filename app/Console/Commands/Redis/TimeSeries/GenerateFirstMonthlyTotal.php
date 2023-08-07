<?php

namespace App\Console\Commands\Redis\TimeSeries;

use App\Models\Location\Country;
use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateFirstMonthlyTotal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:GenerateFirstMonthlyTotal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all of the monthly key-value pairs for all locations started at the first available date.';

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
        $countries = Country::where('manual_verify', true)->get();

        foreach ($countries as $country)
        {
            $photo = Photo::where('country_id', $country->id)->orderBy('id')->first();

            $start = Carbon::parse($photo->created_at)->startOfMonth();

            $end = now()->startOfMonth();

            $currentMonth = $start->copy();

            $total = 0;

            while ($currentMonth->lte($end))
            {
                // format month eg. 10-15
                $formattedMonth = $currentMonth->format('m-y');

                // Check if Redis has data for the month
                $count = (int)Redis::hget("ppm:country:$country->id", $formattedMonth);

                // Add this to the total
                $total += $count;

                // Add the total to Redis for this month
                Redis::hincrby("totalppm:country:$country->id", $formattedMonth, $total);

                $currentMonth->addMonth();
            }
        }
    }
}
