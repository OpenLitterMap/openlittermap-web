<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagsDeletedByAdmin implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var int */
    public $countryId;
    /** @var int */
    public $stateId;
    /** @var int */
    public $cityId;
    /** @var int */
    public $totalTags;
    /** @var int */
    public $totalLitter;
    /** @var int */
    public $totalBrands;
    /** @var array */
    public $deletedLitterTags;
    /** @var array */
    public $deletedBrandsTags;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct (
        Photo $photo,
        int $totalTags,
        int $totalLitter,
        int $totalBrands,
        array $deletedLitterTags,
        array $deletedBrandsTags
    )
    {
        $this->countryId = $photo->country_id;
        $this->stateId = $photo->state_id;
        $this->cityId = $photo->city_id;
        $this->totalTags = $totalTags;
        $this->totalLitter = $totalLitter;
        $this->totalBrands = $totalBrands;
        $this->deletedLitterTags = $deletedLitterTags;
        $this->deletedBrandsTags = $deletedBrandsTags;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('main');
    }
}
