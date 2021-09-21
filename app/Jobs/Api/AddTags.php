<?php

namespace App\Jobs\Api;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsFromPhotoAction;
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

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $photoId
     * @param $tags
     */
    public function __construct ($userId, $photoId, $tags)
    {
        $this->userId = $userId;
        $this->photoId = $photoId;
        $this->tags = $tags;
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

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $this->tags);

        $user->xp += $litterTotals['all'];
        $user->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);

        $photo->total_litter = $litterTotals['litter'];

        if ($user->verification_required)
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
