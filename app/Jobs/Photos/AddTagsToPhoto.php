<?php

namespace App\Jobs\Photos;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\LitterTags;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class AddTagsToPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $photoId, $tags;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($photoId, $tags)
    {
        $this->photoId = $photoId;
        $this->tags = $tags;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $photo = Photo::find($this->photoId);
        $user = User::find($photo->user_id);

        if (! $photo || $photo->verified > 0) return;

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotal = $addTagsAction->run($photo, $this->tags);

        $user->xp += $litterTotal;
        $user->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);

        $photo->remaining = false; // todo
        $photo->total_litter = $litterTotal;

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
