<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;

class FindPhotoByIdController extends Controller
{
    /**
     * Admin can load any photo by its ID
     */
    public function __invoke (Request $request)
    {
        $photo = Photo::with([
            'customTags',
            'user' => function ($q) {
                $q->select('id', 'username');
            }
        ])
        ->where('id', $request['photoId'])
        ->first();

        $photo->tags();

        return [
            'success' => true,
            'photo' => $photo
        ];
    }
}
