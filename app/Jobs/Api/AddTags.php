<?php

namespace App\Jobs\Api;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Models\User\User;
use App\Models\Photo;

use App\Events\TagsVerifiedByAdmin;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $photoId;
    public $tags;
    public $pickedUp;
    public $customTags;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $photoId
     * @param $tags
     * @param $customTags
     * @param $pickedUp
     */
    public function __construct ($userId, $photoId, $tags, $customTags, $pickedUp)
    {
        $this->userId = $userId;
        $this->photoId = $photoId;
        $this->tags = $tags;
        $this->pickedUp = $pickedUp;
        $this->customTags = $customTags;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle ()
    {
        $user = User::find($this->userId);

        $photo = Photo::find($this->photoId);

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

        $photo->total_litter = $litterTotals['litter'];
        $photo->remaining = $this->isLitterRemaining($user);

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

    /**
     * @param $user
     * @return bool
     */
    protected function isLitterRemaining($user): bool
    {
        return is_null($this->pickedUp)
            ? !$user->picked_up
            : !$this->pickedUp;
    }
}
