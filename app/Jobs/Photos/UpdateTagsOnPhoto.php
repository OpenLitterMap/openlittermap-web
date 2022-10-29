<?php

namespace App\Jobs\Photos;

use App\Actions\Photos\Update\UpdatePhotoDecreaseScores;
use App\Models\Photo;
use App\Models\User\User;
use App\Events\TagsVerifiedByAdmin;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateTagsOnPhoto  implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Photo.id
     *
     * @var int
     */
    public $photoId;

    /**
     * Array of pre-defined tags
     *
     * @var array
     */
    public $tags;

    /**
     * Array of custom string tags
     *
     * @var array
     */
    public $customTags;

    /**
     * Is the litter picked up or not?
     *
     * @var bool
     */
    private $pickedUp;

    /**
     * The authenticated User.id who is trying to update a Photo
     *
     * $var int
     */
    private $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct (int $photoId, bool $pickedUp, array $tags = [], array $customTags = [], $userId = null)
    {
        $this->photoId = $photoId;
        $this->tags = $tags;
        $this->customTags = $customTags;
        $this->pickedUp = $pickedUp;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /** @var Photo $photo */
        $photo = Photo::find($this->photoId);
        /** @var User $user */
        $user = User::find($photo->user_id);

        // Extra security step
        if ($user->id !== $this->userId || !$photo) return;

        // Before we update the tags + scores,
        // we need to use the current photo.tags,
        // and remove the scores that were rewarded by this photo

        // This will reverse most of the actions taken by TagsVerifiedByAdmin
        $updatePhotoDecreaseScores = app(UpdatePhotoDecreaseScores::class);
        $updatePhotoDecreaseScores->run($photo, $user->id);

        // Next, delete the existing tags.
        // This will delete the pre-defined Tags, Brands + CustomTags if they exist.
        // This will return the deletedCounts, which we can use to incr/decr the leaderboard scores.
        $deleteTagsFromPhotoAction = app(DeleteTagsFromPhotoAction::class);
        $deletedCounts = $deleteTagsFromPhotoAction->run($photo);

        // Add any pre-defined Tags & Brands to the Photo
        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $this->tags);

        // Add any CustomTags to the Photo
        /** @var AddCustomTagsToPhotoAction $addCustomTagsAction */
        $addCustomTagsAction = app(AddCustomTagsToPhotoAction::class);
        $customTagsTotals = $addCustomTagsAction->run($photo, $this->customTags);

        // Calculate the difference between the original and new sums.
        $newTotal = $litterTotals['all'] + $customTagsTotals;
        $diff = $newTotal - $deletedCounts['all'];

        // This mau increase or decrease the Users xp.
        // Note: We should remove this and use Redis only.
        $user->xp += $diff;
        $user->save();

        /** @var UpdateLeaderboardsForLocationAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, $diff);

        $photo->remaining = !$this->pickedUp;
        $photo->total_litter += $diff;

        if (!$user->is_trusted)
        {
            /* Bring the photo to an initial state of verification */
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }
        else // the user is trusted. Dispatch event to update OLM.
        {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new TagsVerifiedByAdmin($photo->id));
        }

        $photo->save();
    }
}
