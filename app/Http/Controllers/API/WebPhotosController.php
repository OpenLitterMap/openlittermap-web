<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\API\APIPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class WebPhotosController extends Controller
{
    /**
     * Get the total number of photos to tag that were uploaded via web
     *
     * And get the first 10 photos to tag
     */
    public function index ()
    {
        $user = Auth::guard('api')->user();

        $query = APIPhoto::where([
            'user_id' => $user->id,
            'verification' => 0
        ]);

        $photos = null;

        $count = $query->count();

        if ($count > 0) $photos = $query->select('id', 'filename')->take(10)->get();

        return [
            'count' => $count,
            'photos' => $photos
        ];
    }
}
