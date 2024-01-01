<?php

namespace App\Http\Controllers\Admin;

use App\Models\Photo;
use App\Models\User\User;
use App\Actions\CalculateTagsDifferenceAction;
use App\Traits\AddTagsTrait;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class UpdateTagsController extends Controller
{
    use AddTagsTrait;

    public $calculateTagsDiffAction;

    public function __construct (CalculateTagsDifferenceAction $calculateTagsDiffAction)
    {
        $this->middleware('admin');

        $this->calculateTagsDiffAction = $calculateTagsDiffAction;
    }

    /**
     * Update tags on an image
     *
     * Keep the image,
     * Verify the image
     *
     * Decrease the users verification_score they need to reach 1 Littercoin
     * Currently we decrease by 1 but we need to decrease by the amount of edits the Admin made
     */
    public function __invoke (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();

        // $oldTags = $photo->tags();
        // $user = User::find($photo->user_id);

        $updatedTags = $this->addTags($request->tags ?? [], $request->custom_tags ?? [], $request->photoId);

//        // Todo - Add test to show xp is decrementing
//        if (Redis::hexists("user_verification_count", $user->id))
//        {
//            // Todo - decrease by total number of tags changed
//            // Todo - minimum score should be 0
//            Redis::hincrby("user_verification_count", $user->id, ($updatedTags['rewardedAdminXp'] * -1));
//        }

        rewardXpToAdmin(1 + $updatedTags['rewardedAdminXp']);

        logAdminAction($photo, 'update-tags', $updatedTags);

        event (new TagsVerifiedByAdmin($photo->id));

        return [
            'success' => true,
            'updatedTags' => $updatedTags
        ];
    }
}
