<?php /** @noinspection PhpUndefinedFieldInspection */

namespace App\Http\Controllers\Admin;

use App\Events\TagsVerifiedByAdmin;
use App\Litterrata;
use App\Models\AI\Annotation;
use App\Models\Photo;

use App\Traits\AddTagsTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BoundingBoxController extends Controller
{
    use AddTagsTrait;

    /**
     * Allow Admin to add bounding boxes to an image
     *
     * Bring the image to stage 3 level of verifiation
     *
     * 4 = Annotations confirmed by superadmin
     */
    public function create (Request $request)
    {
        $photo = Photo::find($request->photo_id);

        $olm = Litterrata::INSTANCE()->getDecodedJSON();;

        foreach ($request->boxes as $box)
        {
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

            try
            {
                Annotation::create([
                    'photo_id' => $request->photo_id,
                    'category' => $box['category'],
                    'category_id' => $category_id,
                    'tag' => $box['tag'],
                    'tag_id' => $tag_id,
                    'brand' => $box['brand'],
                    'brand_id' => $brand_id,
                    'supercategory_id' => 0, // to-do
                    'segmentation' => null,
                    'bbox' => json_encode($dims),
                    'is_crowd' => false, // is this true because brand + category can be added to an image?
                    'area' => ($box['width'] * $box['height']),
                    'added_by' => auth()->user()->id,
                ]);
            }
            catch (\Exception $e)
            {
                \Log::info(['BoundingBoxController@create', $e->getMessage()]);
            }
        }

        $photo->verified = 3;
        $photo->save();

        return ['success' => true];
    }

    /**
     * Get the next image to add bounding box coordinates
     */
    public function index ()
    {
        $photo = Photo::select(
            'id',
            'filename',
            'smoking_id',
            'food_id',
            'alcohol_id',
            'coffee_id',
            'softdrinks_id',
            'other_id',
            'coastal_id',
            'sanitary_id',
            'dumping_id',
            'industrial_id',
            'brands_id',
            'result_string'
        )
        ->where([
            'verified' => 2,
            'bbox_skipped' => 0,
            ['filename', '!=', '/assets/verified.jpg']
        ])
        ->where('bbox_assigned_to', auth()->user()->id)
        ->orWhere('bbox_assigned_to', null)
        ->first();

        if (is_null($photo->bbox_assigned_to))
        {
            // assign the photo to a user so 2+ users don't load the same photo
            // we should reset th
            $photo->bbox_assigned_to = auth()->user()->id;
            $photo->save();
        }

        // Load the tags for this image
        $photo->tags();

        return $photo;
    }

    /**
     * Mark this image as not compatible for annotations
     */
    public function skip (Request $request)
    {
        $photo = Photo::find($request->photo_id);

        $photo->bbox_skipped = 1;
        $photo->skipped_by = auth()->user()->id;
        $photo->save();

        return ['success' => true];
    }

    /**
     * Update the tags on this image
     */
    public function updateTags (Request $request)
    {
        $photo = Photo::find($request->photoId);

        $this->addTags($request->tags, $request->photoId);

        // todo - dispatch event via horizon
        event (new TagsVerifiedByAdmin($photo->id));

        return ['success' => true];
    }


}
