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
        return Photo::select('id', 'filename', 'result_string')->where([
            'verified' => 2,
        ])->first();
    }


}
