<?php

namespace App\Http\Controllers\API\Tags;

use App\Actions\Tags\AddTagsToPhotoAction;
use App\Http\Requests\Api\PhotoTagsRequest;
use App\Http\Requests\Api\ReplacePhotoTagsRequest;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PhotoTagsController extends Controller
{
    private AddTagsToPhotoAction $addTagsToPhotoActionNew;

    public function __construct(AddTagsToPhotoAction $addTagsToPhotoActionNew)
    {
        $this->addTagsToPhotoActionNew = $addTagsToPhotoActionNew;
    }

    /**
     * Attach tags to a photo.
     */
    public function store(PhotoTagsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $photoTags = $this->addTagsToPhotoActionNew->run(
            Auth::id(),
            $validatedData['photo_id'],
            $validatedData['tags']
        );

        // Mark onboarding complete on first tag submission
        $user = Auth::user();
        if ($user->onboarding_completed_at === null) {
            $user->update(['onboarding_completed_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'photoTags' => $photoTags,
        ]);
    }

    /**
     * Replace all tags on a photo (delete old tags, add new ones).
     * MetricsService handles the delta via processPhoto → doUpdate.
     */
    public function update(ReplacePhotoTagsRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $photo = Photo::findOrFail($validatedData['photo_id']);

        $photoTags = DB::transaction(function () use ($photo, $validatedData) {
            // Delete existing tags (extra_tags cascade via FK)
            $photo->photoTags()->each(function ($tag) {
                $tag->extraTags()->delete();
                $tag->delete();
            });

            // Reset summary and XP
            $photo->update([
                'summary' => null,
                'xp' => 0,
                'total_tags' => 0,
                'total_brands' => 0,
                'result_string' => '',
                'verified' => 0,
            ]);

            // If no new tags, just clear — photo returns to untagged state
            if (empty($validatedData['tags'])) {
                return [];
            }

            // Add new tags (generates summary, XP, fires TagsVerifiedByAdmin → MetricsService)
            return $this->addTagsToPhotoActionNew->run(
                Auth::id(),
                $photo->id,
                $validatedData['tags']
            );
        });

        return response()->json([
            'success' => true,
            'photoTags' => $photoTags,
        ]);
    }
}
