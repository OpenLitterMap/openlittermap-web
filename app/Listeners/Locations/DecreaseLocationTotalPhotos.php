<?php

namespace App\Listeners\Locations;

use App\Actions\Locations\UpdateTotalPhotosForLocationAction;
use App\Events\ImageDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class DecreaseLocationTotalPhotos implements ShouldQueue
{
    /** @var UpdateTotalPhotosForLocationAction */
    protected $updateTotalPhotosAction;

    /**
     * @param UpdateTotalPhotosForLocationAction $updateTotalPhotosAction
     */
    public function __construct(UpdateTotalPhotosForLocationAction $updateTotalPhotosAction)
    {
        $this->updateTotalPhotosAction = $updateTotalPhotosAction;
    }

    /**
     * Update total photos on a redis hash for each location
     */
    public function handle(ImageDeleted $event)
    {
        if (!$event->isUserVerified) {
            return;
        }

        $this->updateTotalPhotosAction->run(
            $event->countryId,
            $event->stateId,
            $event->cityId,
            -1
        );
    }
}
