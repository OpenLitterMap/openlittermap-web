<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Location;
use App\Models\Location\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MoveStateCategoryTotalsToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:move-states-category-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all category totals and move them to redis';

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
        $states = State::all();

        $categoryTotals = Location::getCategoryTotals();
        $brandTotals = Location::getBrandTotals();

        foreach ($states as $state)
        {
            // total_smoking, etc
            foreach ($categoryTotals as $categoryTotal)
            {
                if ($state->$categoryTotal)
                {
                    Redis::del("state:$state->id", $brandTotal);

                    Redis::hincrby("state:$state->id", $categoryTotal, $state->$categoryTotal);
                }
            }

            // total_coke, total_pepsi, etc
            foreach ($brandTotals as $brandTotal)
            {
                if ($state->$brandTotal)
                {
                    Redis::del("state:$state->id", $brandTotal);

                    Redis::hincrby("state:$state->id", $brandTotal, $state->$brandTotal);
                }
            }
        }
    }
}
