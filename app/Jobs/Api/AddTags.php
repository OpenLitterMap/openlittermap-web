<?php

namespace App\Jobs\Api;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use App\Models\User\User;
use App\Models\Photo;

use App\Models\LitterTags;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use App\Events\TagsVerifiedByAdmin;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Redis;

class AddTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $request, $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct ($request, $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle ()
    {
        $user = User::find($this->userId);

        $photo = Photo::find($this->request['photo_id']);

        $tags = $this->request['litter'];

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $tags);

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
