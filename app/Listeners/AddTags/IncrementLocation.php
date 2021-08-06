<?php

namespace App\Listeners\AddTags;

use App\Actions\Locations\UpdateTotalPhotosForLocationAction;
use App\Events\TagsVerifiedByAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class IncrementLocation implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param TagsVerifiedByAdmin $event
     * @return void
     */
    public function handle(TagsVerifiedByAdmin $event)
    {
        $this->increaseTotalsForLitter($event);

        $this->increaseTotalsForBrands($event);

        $this->increaseTotalPhotos($event);
    }

    /**
     * Increases litter and total_litter for every location
     *
     * @param TagsVerifiedByAdmin $event
     */
    protected function increaseTotalsForLitter(TagsVerifiedByAdmin $event): void
    {
        if ($event->total_litter_all_categories > 0)
        {
            foreach ($event->total_litter_per_category as $category => $total)
            {
                Redis::hincrby("country:$event->country_id", $category, $total);
                Redis::hincrby("state:$event->state_id", $category, $total);
                Redis::hincrby("city:$event->city_id", $category, $total);
            }

            Redis::hincrby("country:$event->country_id", "total_litter", $event->total_litter_all_categories);
            Redis::hincrby("state:$event->state_id", "total_litter", $event->total_litter_all_categories);
            Redis::hincrby("city:$event->city_id", "total_litter", $event->total_litter_all_categories);
        }
    }

    /**
     * Increases brand and total_brands for every location
     *
     * @param TagsVerifiedByAdmin $event
     */
    protected function increaseTotalsForBrands(TagsVerifiedByAdmin $event): void
    {
        if ($event->total_brands > 0)
        {
            foreach ($event->total_litter_per_brand as $brand => $total)
            {
                Redis::hincrby("country:$event->country_id", $brand, $total);
                Redis::hincrby("state:$event->state_id", $brand, $total);
                Redis::hincrby("city:$event->city_id", $brand, $total);
            }

            Redis::hincrby("country:$event->country_id", "total_brands", $event->total_brands);
            Redis::hincrby("state:$event->state_id", "total_brands", $event->total_brands);
            Redis::hincrby("city:$event->city_id", "total_brands", $event->total_brands);
        }
    }

    /**
     * Increases the total_photos value for every location
     * If a user is already verified, we don't need to increase this value
     * because it is already incremented during photo upload
     *
     * @param TagsVerifiedByAdmin $event
     */
    protected function increaseTotalPhotos(TagsVerifiedByAdmin $event): void
    {
        if ($event->isUserVerified)
        {
            return;
        }

        /** @var UpdateTotalPhotosForLocationAction $updateTotalPhotosAction */
        $updateTotalPhotosAction = app(UpdateTotalPhotosForLocationAction::class);
        $updateTotalPhotosAction->run(
            $event->country_id,
            $event->state_id,
            $event->city_id
        );
    }
}
