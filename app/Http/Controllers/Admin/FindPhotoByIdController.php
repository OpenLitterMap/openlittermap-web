<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindPhotoByIdController extends Controller
{
    /**
     * Admin can load any photo by its ID
     */
    public function __invoke(Request $request): JsonResponse
    {
        $photo = Photo::with([
            'customTags',
            'user' => function ($q): void {
                $q->select('id', 'username');
            },
        ])
            ->where('id', $request['photoId'])
            ->first();

        if (! $photo) {
            return response()->json([
                'success' => false,
                'photo' => null,
            ]);
        }

        $photo->tags();

        return response()->json([
            'success' => true,
            'photo' => $photo,
        ]);
    }
}
