<?php

namespace App\Http\Controllers\Bbox;

use App\Events\Littercoin\LittercoinMined;
use App\Http\Controllers\Controller;
use App\Litterrata;
use App\Models\AI\Annotation;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;

class VerifyBoxController extends Controller
{
    /**
     * Get the next image with boxes to verify
     *
     * Don't load an image the user created boxes for
     *
     * Todo - show level 4 counts so far
     *
     * @return array
     */
    public function index ()
    {
        $userId = auth()->user()->id;

        // Load the next photo to verify, or the previous one assigned to the active user
        $photo = Photo::with('boxes')
            ->where([
                'verified' => 3,
                'bbox_verification_assigned_to' => $userId,
                'bbox_skipped' => false,
                ['five_hundred_square_filepath', '!=', null]
            ])->first();

        // orWhere not working?
        if (! $photo)
        {
            $photo = Photo::with('boxes')
                ->where([
                    'verified' => 3,
                    'bbox_verification_assigned_to' => null,
                    'bbox_skipped' => false,
                    ['five_hundred_square_filepath', '!=', null]
                ])->first();
        }

        if (! $photo) {
            return ['photo' => null];
        }

        $photo->bbox_verification_assigned_to = $userId;
        $photo->save();

        // load the tags for the image
        $photo->tags();

//        $totalBoxCount = 0;
//        if ($photo['boxes'])
//        {
//            $totalBoxCount = $photo['boxes'][0]->id;
//        }
//
//        $usersBoxCount = Photo::where(['verified' => 4, 'verified_by' => $userId])->count();

        return [
            'photo' => $photo,
//            'totalBoxCount' => $totalBoxCount,
//            'usersBoxCount' => $usersBoxCount
        ];
    }

    /**
     * Verify the boxes and tags are correct
     *
     * if hasChanged is true, update boxes for the image
     *
     * Add "boxes_verified_by"
     *
     * @return array
     */
    public function update (Request $request)
    {
        $photo = Photo::find($request->photo_id);

        // increment Littercoin reward for person who added the boxes
        // increment Littercoin reward for person who verified the boxes
        // increment XP for person doing the verification
        $userAddedBoxes = User::where('id', $photo->bbox_assigned_to)->first();
        $userDoingVerification = auth()->user();

        $olm = Litterrata::INSTANCE()->getDecodedJSON();;

        foreach ($request->boxes as $box)
        {
            if ($request->hasChanged)
            {
                $annotation = Annotation::where(['id' => $box['id'], 'photo_id' => $photo->id])->first();

                if (! $annotation)
                {
                    $annotation = new Annotation();
                    $annotation->photo_id = $photo->id;
                }

                $dims = [$box['left'], $box['top'], $box['width'], $box['height']];

                $category = $box['category'];
                $tag = $box['tag'];

                $category_id = $olm->categories->$category;
                $tag_id = $olm->$category->$tag;

                $brand_id = null;
                $brand = null;

                if ($box['brand'])
                {
                    $brand = $box['brand'];

                    $brand_id = $olm->brands->$brand;
                }

                $annotation->category = $category;
                $annotation->category_id = $category_id;
                $annotation->tag = $tag;
                $annotation->tag_id = $tag_id;
                $annotation->brand = $brand;
                $annotation->brand_id = $brand_id;
                $annotation->supercategory_id = 0; // to
                $annotation->segmentation = null;
                $annotation->bbox = json_encode($dims);
                $annotation->is_crowd = false; // is this true because brand + category can be added to an image
                $annotation->area = ($box['width'] * $box['height']);
                $annotation->verified_by = $userDoingVerification->id;

                $annotation->save();
            }

            // Update Littercoin and XP
            // Todo - move this outside of the foreach loop to avoid additional requests
            // we need to make sure Littercoin is rewarded at 100 and additional counts are rewarded
            $userAddedBoxes->bbox_verification_count++;

            if ($userAddedBoxes->bbox_verification_count === 100)
            {
                $userAddedBoxes->bbox_verification_count = 0;
                $userAddedBoxes->littercoin_owed++;

                event (new LittercoinMined($userAddedBoxes->id, 'verified-box'));
            }

            $userAddedBoxes->save();

            $userDoingVerification->xp++;
            $userDoingVerification->save();
        }

        $photo->verified = 4;
        $photo->verified_by = $userDoingVerification->id;
        $photo->save();

        return ['success' => true];
    }
}
