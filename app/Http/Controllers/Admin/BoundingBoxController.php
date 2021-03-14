<?php

namespace App\Http\Controllers\Admin;

use App\Models\AI\Annotation;
use App\Models\Photo;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BoundingBoxController extends Controller
{
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

        foreach ($request->boxes as $box)
        {
            Annotation::create([
                'photo_id' => $photo->id,
                'category_id' => 0,
                'supercategory_id' => 0,
                'segmentation' => null,
                'bbox' => '', // json_encode(left, top, width, height) ?
                'is_crowd' => false, // ?
                'area' => 0,
                // 'added_by' => auth()->user()->id,
                // 'verified_by' => null
            ]);
        }
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
            'brands_id'
        )
        ->where(['verified' => 2])
        ->first();

        $photo->tags();

        return $photo;
    }


}
