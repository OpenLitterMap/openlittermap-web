<?php

namespace App\Events;

use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoTag;
use App\Models\User\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TagsVerifiedByAdmin implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // photo relationships
    public $photo_id, $city_id, $state_id, $country_id, $user_id, $created_at;

    // total litter on all categories
    public $total_litter_all_categories, $total_brands;

    // total per category, or total per brand
    public $total_litter_per_category = []; // smoking => 5, alcohol => 1
    public $total_litter_per_brand = []; // mcd => 1, starbucks => 2

    /** @var bool */
    public $isUserVerified;

    /**
     * The tags on a single photo have been verified by an Admin
     *
     * photo.verified => 2
     */
    public function __construct ($photo_id)
    {
        /** @var Photo $photo */
        $photo = Photo::with('tags.category')->find($photo_id);
        $this->photo_id = $photo_id;

        $this->city_id = $photo->city_id;
        $this->state_id = $photo->state_id;
        $this->country_id = $photo->country_id;
        $this->user_id = $photo->user_id;
        $this->created_at = $photo->created_at;

        $total_litter_all_categories = 0;

        // Count the total category values on this photo
        // We will use this data to update the total category values...
        // for each Country, State and City the photo was uploaded from
        /** @var Category $category */
        foreach (Category::with('tags')->get() as $category) {
            $categoryTotal = PhotoTag::query()
                ->where(['photo_id' => $photo->id])
                ->whereIn('tag_id', $category->tags()->get('id'))
                ->sum('quantity');

            // This parent class will hold each category total
            // and use it to update each listener
            $this->total_litter_per_category[$category->name] = $categoryTotal;

            $total_litter_all_categories += $categoryTotal;
        }

        $this->total_litter_all_categories = $total_litter_all_categories;

        $this->isUserVerified = User::find($this->user_id)->is_trusted;
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
