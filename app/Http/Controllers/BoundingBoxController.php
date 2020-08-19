<?php

namespace App\Http\Controllers;

use App\Photo;
use Illuminate\Http\Request;

class BoundingBoxController extends Controller
{
    /**
     * Get the next image to add bounding box coordinates
     */
    public function index ()
    {
        return Photo::with('smoking', 'food', 'alcohol', 'coffee', 'softdrinks')
            ->select('id', 'filename', 'result_string')->where([
                'verified' => 2,
                'bounding_box' => null
            ])->first();
    }


}
