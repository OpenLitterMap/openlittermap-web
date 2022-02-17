<?php

namespace App\Jobs\Photos;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddTagsToPhoto implements ShouldQueue
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
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($photoId, array $tags = [], array $customTags = [])
    {
        $this->photoId = $photoId;
        $this->tags = $tags;
        $this->customTags = $customTags;
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

        if (! $photo || $photo->verified > 0) return;

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

        $photo->remaining = false; // todo
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
