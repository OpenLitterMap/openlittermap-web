<?php

namespace App\Jobs\Api;

use App\Models\Photo;
use App\Models\User\User;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use App\Events\TagsVerifiedByAdmin;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AddTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public int $photoId;
    public mixed $tags;
    public mixed $customTags;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $photoId
     * @param $tags
     * @param $customTags
     */
    public function __construct ($userId, $photoId, $tags, $customTags)
    {
        $this->userId = $userId;
        $this->photoId = $photoId;
        $this->tags = $tags;
        $this->customTags = $customTags;
    }

    public function handle (): void
    {
        $litterTotals['all'] = 0;
        $litterTotals['litter'] = 0;
        $customTagsTotals = 0;

        $user = User::find($this->userId);
        $photo = Photo::find($this->photoId);

        if ($this->tags)
        {
            $tags = (gettype($this->tags) === 'string')
                ? json_decode($this->tags, true)
                : $this->tags;

            $addTagsAction = app(AddTagsToPhotoAction::class);
            $litterTotals = $addTagsAction->run($photo, $tags);
        }

        if ($this->customTags && $this->customTags !== "undefined")
        {
            $addCustomTagsAction = app(AddCustomTagsToPhotoAction::class);

            $customTags = (gettype($this->customTags) === 'string')
                ? json_decode($this->customTags, true)
                : $this->customTags;

            $customTagsTotals = $addCustomTagsAction->run($photo, $customTags);
        }

        // This will also update XP on redis
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, $litterTotals['all'] + $customTagsTotals);

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
