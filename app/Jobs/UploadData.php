<?php

namespace App\Jobs;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\User\User;
use App\Models\Photo;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UploadData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $request;
    public $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request, $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::find($this->userId);
        $photo = Photo::find($this->request['photo_id']);

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $totalLitter = $addTagsAction->run($photo, $this->request['litter']);

        $user->xp += $totalLitter;
        $user->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);

        // $photo->remaining = $this->request->presence ?? false;
        $photo->total_litter = $totalLitter;

        // Check if the User is a trusted user => photos do not require verification.
        if ($user->verification_required == 0)
        {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new TagsVerifiedByAdmin($photo->id));
        }

        else
        {
            // Bring the photo to an initial state of verification
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }

        $photo->save();
    }
}
