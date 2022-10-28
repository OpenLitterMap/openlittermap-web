<?php

namespace App\Jobs\Photos;

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

    public $photoId;
    /**
     * @var array
     */
    public $tags;
    /**
     * @var array
     */
    public $customTags;
    /**
     * @var bool
     */
    private $pickedUp;
    /**
     * $var int
     */
    private $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct (
        int $photoId, bool $pickedUp,
        array $tags = [],
        array $customTags = [],
        $userId = null
    )
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
        if ($user->id !== $this->userId) return;

        if (!$photo) return;

        // Delete Tags, Brands + CustomTags if they exist
        $deleteTagsFromPhotoAction = app(DeleteTagsFromPhotoAction::class);

        // we will use deletedCount to calculate the newXp we should give to each Location
        $deletedCount = $deleteTagsFromPhotoAction->run($photo);

        /** @var AddCustomTagsToPhotoAction $addCustomTagsAction */
        $addCustomTagsAction = app(AddCustomTagsToPhotoAction::class);
        $customTagsTotals = $addCustomTagsAction->run($photo, $this->customTags);

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $this->tags);

        $user->xp += $litterTotals['all'] + $customTagsTotals;
        $user->save();

        /** @var UpdateLeaderboardsForLocationAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, $litterTotals['all'] + $customTagsTotals);

        $photo->remaining = !$this->pickedUp;
        $photo->total_litter = $litterTotals['litter'];

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
