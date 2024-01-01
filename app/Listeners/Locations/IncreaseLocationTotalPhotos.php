<?php

namespace App\Listeners\Locations;

use App\Actions\Locations\UpdateTotalPhotosForLocationAction;
use App\Events\ImageUploaded;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncreaseLocationTotalPhotos implements ShouldQueue
{
    /** @var UpdateTotalPhotosForLocationAction */
    protected $updateTotalPhotosAction;

    public function __construct(UpdateTotalPhotosForLocationAction $updateTotalPhotosAction)
    {
        $this->updateTotalPhotosAction = $updateTotalPhotosAction;
    }

    /**
     * Update total photos on a redis hash for each location
     */
    public function handle(ImageUploaded $event)
    {
        if (!$event->isUserVerified) {
            return;
        }

        $this->updateTotalPhotosAction->run(
            $event->countryId,
            $event->stateId,
            $event->cityId
        );
    }
}
