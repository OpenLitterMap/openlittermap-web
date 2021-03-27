<?php

namespace App\Http\Controllers\Bbox;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;

class VerifyBoxController extends Controller
{
    /**
     * Get the next image with boxes to verify
     */
    public function index ()
    {
        $photo = Photo::with('boxes')
            ->where([
                'verified' => 3
            ])->first();

        $photo->tags();

        return ['photo' => $photo];
    }
}
