<?php

namespace App\Http\Controllers\API\Tags;

use App\Actions\Tags\AddTagsToPhotoActionNew;
use App\Http\Requests\Api\PhotoTagsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PhotoTagsController extends Controller
{
    private AddTagsToPhotoActionNew $addTagsToPhotoActionNew;

    public function __construct(AddTagsToPhotoActionNew $addTagsToPhotoActionNew)
    {
        $this->addTagsToPhotoActionNew = $addTagsToPhotoActionNew;
    }

    /**
     * Attach tags to a photo.
     *
     * @param PhotoTagsRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store (PhotoTagsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $photoTags = $this->addTagsToPhotoActionNew->run(
            Auth::id(),
            $validatedData['photo_id'],
            $validatedData['tags']
        );

        return response()->json([
            'success' => true,
            'photoTags' => $photoTags,
        ]);
    }
}
