<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Location;
use App\Models\Location\State;
use App\Models\Photo;
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

        $categories = Photo::categories();
        $brands = Photo::getBrands();

        foreach ($states as $state)
        {
            foreach ($categories as $category)
            {
                $total_category = "total_$category";

                if ($state->$total_category)
                {
                    Redis::hdel("state:$state->id", $category);

                    Redis::hincrby("state:$state->id", $category, $state->$total_category);
                }
            }

            foreach ($brands as $brand)
            {
                $total_brand = "total_$brand";

                if ($state->$total_brand)
                {
                    Redis::hdel("state:$state->id", $brand);

                    Redis::hincrby("state:$state->id", $brand, $state->$total_brand);
                }
            }
        }
    }
}
