<?php

namespace App\Http\Controllers\Admin;

use App\Models\Photo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoBackOnePhotoController extends Controller
{
    /**
     * When an Admin is verifying images,
     *
     * Allow them to go back 1 photo
     */
    public function __invoke (Request $request)
    {
        $photoId = (int)$request['photoId'];
        $filterMyOwnPhotos = (boolean)$request['filterMyOwnPhotos'];

        $userId = auth()->user()->id;
        $query = Photo::query();

        if ($filterMyOwnPhotos)
        {
            $query->where('user_id', $userId)
                  ->where('id', '<', $photoId)
                  ->orderBy('id', 'desc');
        }
        else
        {
            $photoId -= 1;

            $query->where('id', $photoId);
        }

        $photo = $query->with([
            'customTags',
            'user' => function ($q) {
                $q->select('id', 'username');
            }
        ])->first();

        if (!$photo)
        {
            return [
                'success' => false,
                'msg' => 'photo not found'
            ];
        }

        // Load the tags for a Photo
        $photo->tags();

        return [
            'success' => true,
            'photo' => $photo
        ];
    }
}
