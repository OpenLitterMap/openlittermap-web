<?php

namespace App\Listeners\UpdateTags;

use App\Events\TagsDeletedByAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class DecrementLocation implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param TagsDeletedByAdmin $event
     * @return void
     */
    public function handle(TagsDeletedByAdmin $event)
    {
        $this->decreaseTotalsForLitter($event);

        $this->decreaseTotalsForBrands($event);
    }

    /**
     * Decreases litter and total_litter for every location
     *
     * @param TagsDeletedByAdmin $event
     */
    protected function decreaseTotalsForLitter(TagsDeletedByAdmin $event): void
    {
        if (!$event->totalLitter) {
            return;
        }

        foreach ($event->deletedLitterTags as $category => $total)
        {
            Redis::hincrby("country:$event->countryId", $category, -$total);
            Redis::hincrby("state:$event->stateId", $category, -$total);
            Redis::hincrby("city:$event->cityId", $category, -$total);
        }

        Redis::hincrby("country:$event->countryId", "total_litter", $event->totalLitter);
        Redis::hincrby("state:$event->stateId", "total_litter", $event->totalLitter);
        Redis::hincrby("city:$event->cityId", "total_litter", $event->totalLitter);
    }

    /**
     * Decreases brand and total_brands for every location
     *
     * @param TagsDeletedByAdmin $event
     */
    protected function decreaseTotalsForBrands(TagsDeletedByAdmin $event): void
    {
        if (!$event->totalBrands)
        {
            return;
        }

        foreach ($event->deletedBrandsTags as $brand => $total)
        {
            Redis::hincrby("country:$event->countryId", $brand, -$total);
            Redis::hincrby("state:$event->stateId", $brand, -$total);
            Redis::hincrby("city:$event->cityId", $brand, -$total);
        }

        Redis::hincrby("country:$event->countryId", "total_brands", $event->totalBrands);
        Redis::hincrby("state:$event->stateId", "total_brands", $event->totalBrands);
        Redis::hincrby("city:$event->cityId", "total_brands", $event->totalBrands);
    }
}
