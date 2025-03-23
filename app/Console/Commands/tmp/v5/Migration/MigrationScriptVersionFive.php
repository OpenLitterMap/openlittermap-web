<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
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

    // upgrade older tags to use newer format
    protected function updateTags (Photo $photo): void {

        $tags = $photo->tags;

        // loop over them
        foreach ($tags as $category => $categoryTags) {

            $newCategory = Category::firstOrCreate(['key' => $category]);

            $photoTag = PhotoTag::firstOrCreate([
                'photo_id' => $photo->id,
                'category_id' => $newCategory->id,
            ]);

            foreach ($categoryTags as $tag => $quantity) {
                $newObject = LitterObject::firstOrCreate(['key' => $tag]);

                if ($photoTag->litter_object_id === null) {
                    $photoTag->litter_object_id = $newObject->id;
                    $photoTag->quantity = $quantity;
                    $photoTag->save();
                } else {
                    $photoTag = PhotoTag::firstOrCreate([
                        'photo_id' => $photo->id,
                        'category_id' => $newCategory->id,
                        'litter_object_id' => $newObject->id,
                        'quantity' => $quantity
                    ]);
                }

                if ($category === "material") {
                    $newMaterial = Materials::firstOrCreate(['key' => $tag]);

                    if ($photoTag->material_id === null) {
                        $photoTag->material_id = $newMaterial->id;
                        $photoTag->save();
                    } else {
                        $photoTag = PhotoTag::firstOrCreate([
                            'photo_id' => $photo->id,
                            'category_id' => $newCategory?->id,
                            'litter_object_id' => $newObject?->id,
                            'material_id' => $newMaterial->id,
                            'quantity' => $quantity
                        ]);
                    }
                }
            }
        }
    }

    protected function updateTotals (Photo $photo): void {

        $locations = [
            'global',
            'country',
            'state',
            'city'
        ];

        // v1 tags
        $tags = $photo->tags;
        $customTags = $photo->custom_tags;
        $country = $photo->country;
        $state = $photo->state;
        $city = $photo->city;

        // We need to get these from the original v1 tags
        $categories = $photo->categories;
        $objects = $categories->objects;
        $materials = $photo->materials;
        $brands = $photo->brands;

        $tagsCount = $tags->count();
        $customTagsCount = $customTags->count();

        foreach ($locations as $location) {

            if ($location === 'global') {
                $key = "global";
            } else {
                if ($location === "country") {
                    $key = "country:$country->id";
                } elseif ($location === "state") {
                    $key = "state:$state->id";
                } elseif ($location === "city") {
                    $key = "city:$city->id";
                }
            }

            Redis::hincrby("$key:totals", 'photos', 1);
            Redis::hincrby("$key:totals", 'tags', $tagsCount);
            Redis::hincrby("$key:totals", 'custom_tags', $customTagsCount);

            foreach ($categories as $category) {

                $categoryId = Category::where('key', $category)->first()->id;

                Redis::hincrby("$key:totals:category", $categoryId, 1);
            }

            // g.categories.category.object.material
            foreach ($objects as $object) {
                $objectId = LitterObject::where('key', $object)->first()->id;

                Redis::hincrby("$key:totals:objects", $objectId, 1);
            }

            foreach ($materials as $material) {
                $materialId = Materials::where('key', $material)->first()->id;

                Redis::hincrby("$key:materials", $materialId, 1);
            }

            foreach ($brands as $brand) {
                $brandId = BrandList::where('key', $brand)->first()->id;

                Redis::hincrby("$key:totals:brands", $brandId, 1);
            }
        }
    }

    public function updateTimeSeries(Photo $photo): void {
        $created_at = $photo->created_at;
        $taken_at = $photo->datetime;

        Redis::hincrby("test", 1);

        // global:timeseries:ppd:yyyy:mm:dd
        // country:id:totals.timeseries.photos:yyyy:mm:dd
        // state:id:totals.timeseries.photos:yyyy:mm:dd
        // city:id:totals.timeseries.photos:yyyy:mm:dd
        // user:id:totals.timeseries.photos:yyyy:mm:dd

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
