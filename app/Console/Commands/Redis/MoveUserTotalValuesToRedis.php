<?php

namespace App\Console\Commands\Redis;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MoveUserTotalValuesToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:move-user-total-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take all user total database quantities and move them to redis';

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
        $users = User::where('has_uploaded', 1)->get();

        $categories = Photo::categories();
        $brands = Photo::getBrands();

        foreach ($users as $user)
        {
            $total_litter = 0;
            $total_brands = 0;

            Redis::hdel("user:$user->id", "total_litter");
            Redis::hdel("user:$user->id", "total_brands");

            foreach ($categories as $category)
            {
                $total_category = "total_$category";

                if ($user->$total_category)
                {
                    Redis::hdel("user:$user->id", $category);

                    Redis::hincrby("user:$user->id", $category, $user->$total_category);

                    $total_litter += $user->$total_category;
                }
            }

//            foreach ($brands as $brand)
//            {
//                $total_brand = "total_$brand";
//
//                if ($user->$total_brand)
//                {
//                    Redis::hdel("user:$user->id", $brand);
//
//                    Redis::hincrby("user:$user->id", $brand, $user->$total_brand);
//                }
//            }
        }
    }
}
