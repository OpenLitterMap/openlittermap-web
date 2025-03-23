<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class MigrationScriptVersionFive extends Command
{
    protected $signature = 'olm:v5';

    protected $description = 'Upgrade OpenLitterMap data to v5';

    public function handle(): void
    {
        // Loop over all photos
        $photos = Photo::query()
            ->select('id', 'datetime', 'user_id', 'country_id', 'state_id', 'city_id')
            ->orderBy('id', 'desc');

        foreach ($photos->cursor() as $photo)
        {
            $this->updateTags($photo);

            $this->updateTotals($photo);

            $this->updateTimeSeries($photo);

            $this->updateLeaderboards($photo);

            $this->updateUserAchievements($photo);
        }
    }

    protected function updateTags (Photo $photo) {
        // get old tags
        // loop over them

        // try to insert into new format
        // if tag fails, log it
        // if exists, add to new PhotoTag format
    }

    protected function updateTotals (Photo $photo) {

        // get new tags for photo and update totals

        // global.totals.tags
        // global.totals.custom_tags
        // global.totals.categories
        // global.totals.litter
        // global.totals.brands

        // country.totals.tags
        // country.totals.custom_tags
        // country.totals.categories
        // country.totals.litter
        // country.totals.brands

        // state.totals.tags
        // state.totals.custom_tags
        // state.totals.categories
        // state.totals.litter
        // state.totals.brands

        // global.categories.category
        // country.categories.category
        // state.categories.category
    }

    protected function updateTimeSeries(Photo $photo) {

        Redis::hincrby('global:totals', 'photos', 1);

        // get date for photo

        // country:id total_photos:yyyy:mm:dd
        // state:id total_photos:yyyy:mm:dd:
        // city:id total_photos:yyyy:mm:dd
        // user:id total_photos:yyyy:mm:dd

        // country:id:photos_per_day:yyyy:mm:dd
        // state:id:photos_per_day:yyyy:mm:dd
        // city:id:photos_per_day:yyyy:mm:dd
        // user:id:photos_per_day:yyyy:mm:dd

        // country:id:photos_per_week:yyyy:ww
        // state:id:photos_per_week:yyyy:ww
        // city:id:photos_per_week:yyyy:ww
        // user:id:photos_per_week:yyyy:ww

        // country:id:photos_per_month:yyyy:mm
        // state:id:photos_per_month:yyyy:mm
        // city:id:photos_per_month:yyyy:mm
        // user:id:photos_per_month:yyyy:mm

        // country:id:photos_per_year:yyyy
        // state:id:photos_per_year:yyyy
        // city:id:photos_per_year:yyyy
        // user:id:photos_per_year:yyyy
    }

    protected function updateLeaderboards(Photo $photo) {

        // get xp for photo

        // leaderboard:users:yyyy:mm:dd
        // leaderboard:locationType:locationId:yyyy:mm:dd
    }

    protected function updateUserAchievements (Photo $photo) {

        // uploaded x days in a row
        // track days uploaded in a row
    }
}
