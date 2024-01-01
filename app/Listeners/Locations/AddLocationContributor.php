<?php

namespace App\Listeners\Locations;

use App\Actions\Locations\AddContributorForLocationAction;
use App\Events\ImageUploaded;
use Illuminate\Contracts\Queue\ShouldQueue;

class AddLocationContributor implements ShouldQueue
{
    /** @var AddContributorForLocationAction */
    protected $addContributorAction;

    public function __construct(AddContributorForLocationAction $addContributorAction)
    {
        $this->addContributorAction = $addContributorAction;
    }

    /**
     * Add user_id to a redis set for each location
     */
    public function handle (ImageUploaded $event)
    {
        $this->addContributorAction->run(
            $event->countryId,
            $event->stateId,
            $event->cityId,
            $event->userId
        );
    }
}
