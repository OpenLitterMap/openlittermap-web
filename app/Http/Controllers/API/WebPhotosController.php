<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\API\APIPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebPhotosController extends Controller
{
    /**
     * Load the next 10 images that were uploaded via web
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function loadMore ()
    {
        $user = Auth::guard('api')->user();

        return APIPhoto::where([
            'user_id' => $user->id,
            'verification' => 0,
            ['id', '>', request()->photo_id]
        ])
        ->select('id', 'filename')
        ->orderBy('id')
        ->take(10)
        ->get();
    }
}
