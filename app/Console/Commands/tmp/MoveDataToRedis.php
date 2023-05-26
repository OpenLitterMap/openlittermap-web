<?php

namespace App\Console\Commands\tmp;

use App\Models\Photo;
use Illuminate\Console\Command;

class MoveDataToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:move-data-to-redis-2023';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move data to redis';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $photos = Photo::query()
            ->select(
                'id',
                'datetime',
                'user_id',
                'country_id',
                'state_id',
                'city_id'
            )
            ->orderBy('id', 'desc');

        $total = $photos->count();

        // we have:
        // user:id category total
        // user:id total_litter
        // user:id brand total
        // user:id total_brands
        // user:id total_photos

        // leaderboard:users:yyyy:mm:dd
        // leaderboard:locationType:locationId:yyyy:mm:dd

        // we need
        // user:id total_custom_tags?

        foreach ($photos->cursor() as $photo)
        {
            // Each Photo
            // total_litter
            // total_brands
            // total_custom_tags // the sum of these gives us total_tags
            // total_categories // include each category and its score

            // Globally
            // photos per day, month, year
            // cumulative photos over time

            // Each Location
            // - total photos
            // - photos per day, month, year
            // - total_litter
            // - total_brands
            // - total_custom_tags // the sum of these gives us total tags
            // - total_categories // include each category and its score

        }
    }
}
