<?php

namespace App\Listeners\Locations;

use App\Actions\Locations\RemoveContributorForLocationAction;
use App\Events\ImageDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemoveLocationContributor implements ShouldQueue
{
    /** @var RemoveContributorForLocationAction */
    protected $removeContributorAction;

    /**
     * @param RemoveContributorForLocationAction $removeContributorAction
     */
    public function __construct(RemoveContributorForLocationAction $removeContributorAction)
    {
        $this->removeContributorAction = $removeContributorAction;
    }

    /**
     * Remove user_id from a redis set for each location
     */
    public function handle (ImageDeleted $event)
    {
        // If the user has at least one photo
        // they are still considered a contributor
        // So don't remove them
        if ($event->user->total_images) {
            return;
        }

        $this->removeContributorAction->run(
            $event->countryId,
            $event->stateId,
            $event->cityId,
            $event->user->id
        );
    }
}
