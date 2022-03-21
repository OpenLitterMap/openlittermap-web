<?php /** @noinspection PhpUndefinedFieldInspection */

namespace App\Http\Controllers\Bbox;

use App\Actions\CalculateTagsDifferenceAction;
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

    /** @var CalculateTagsDifferenceAction */
    protected $calculateTagsDiffAction;

    /**
     * @param CalculateTagsDifferenceAction $calculateTagsDiffAction
     */
    public function __construct(CalculateTagsDifferenceAction $calculateTagsDiffAction)
    {
        $this->calculateTagsDiffAction = $calculateTagsDiffAction;
    }

    /**
     * Add bounding boxes to an image
     *
     * Bring the image to stage 3 level of verifiation
     *
     * 4 = Annotations confirmed by superadmin
     *
     * @return array
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
     *
     * @return array
     */
    public function index ()
    {
        $columns = [
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
            'result_string',
            'five_hundred_square_filepath'
        ];

        $userId = auth()->user()->id;

        // Continue after photo.id 25921

        // Get the previous photo assigned to the user
        $photo = Photo::select($columns)
            ->where([
                'verified' => 2,
                'bbox_skipped' => 0,
                ['filename', '!=', '/assets/verified.jpg'],
                'bbox_assigned_to' => $userId,
                'wrong_tags' => false,
                'total_litter' => 1,
                ['five_hundred_square_filepath', '!=', null]
            ])->first();

        // Or, get the next available photo
        if (! $photo)
        {
            $photo = Photo::select($columns)
                ->where([
                    'verified' => 2,
                    'bbox_skipped' => 0,
                    ['filename', '!=', '/assets/verified.jpg'],
                    'bbox_assigned_to' => null,
                    'wrong_tags' => false,
                    'total_litter' => 1,
                    ['five_hundred_square_filepath', '!=', null]
                ])->first();

            // Assign the photo to a user so 2+ users don't load the same photo
            // This should be reset eventually
            $photo->bbox_assigned_to = $userId;
            $photo->save();
        }

        // Load the tags for this image
        $photo->tags();

        $totalBoxCount = Annotation::count();
        $usersBoxCount = Annotation::where('added_by', $userId)->count();

        return [
            'photo' => $photo,
            'totalBoxCount' => $totalBoxCount,
            'usersBoxCount' => $usersBoxCount
        ];
    }

    /**
     * Mark this image as not compatible for annotations
     *
     * @return array
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

     * Admin only
     *
     * @return array
     */
    public function updateTags (Request $request)
    {
        // if Admin
        if (auth()->user()->can('update tags'))
        {
            $photo = Photo::find($request->photoId);

            $this->addTags($request->tags, [], $request->photoId);

            // todo - dispatch event via horizon
            event(new TagsVerifiedByAdmin($photo->id));

            return ['success' => true];
        }

        return ['success' => false, 'msg' => 'not-admin'];
    }

    /**
     * Non-admin can mark the image as having wrong tags
     *
     * This user will not see this image again, but another user will.
     * This helps us prevent against
     *
     * Only admin can update the tags
     *
     * @return array
     */
    public function wrongTags (Request $request)
    {
        $photo = Photo::find($request->photoId);

        $photo->wrong_tags = auth()->user()->id;
        $photo->save();

        return ['success' => true];
    }
}
