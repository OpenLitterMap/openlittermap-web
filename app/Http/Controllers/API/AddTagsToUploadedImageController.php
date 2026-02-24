<?php

namespace App\Http\Controllers\API;

use App\Actions\Tags\ConvertV4TagsAction;
use App\Enums\VerificationStatus;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddTagsRequest;

class AddTagsToUploadedImageController extends Controller
{
    /**
     * Save litter data to a recently uploaded photo
     * Note: when photo was uploaded, picked_up was set
     *
     * version 2.2
     */
    public function __invoke (AddTagsRequest $request): JsonResponse
    {
        $user = auth()->user();
        $photo = Photo::find($request->photo_id);

        if ($photo->user_id !== $user->id || $photo->verified->value > VerificationStatus::UNVERIFIED->value)
        {
            abort(403, 'Forbidden');
        }

        Log::channel('tags')->info([
            'add_tags' => 'mobile',
            'request' => $request->all()
        ]);

        $v4Tags = ($request->litter ?? $request->tags) ?? [];
        if (is_string($v4Tags)) {
            $v4Tags = json_decode($v4Tags, true) ?? [];
        }

        $customTags = $request->custom_tags ?? [];
        if (is_string($customTags)) {
            $customTags = json_decode($customTags, true) ?? [];
        }

        $pickedUp = (isset($request->picked_up) && ! is_null($request->picked_up))
            ? (bool) $request->picked_up
            : ! $user->items_remaining;

        app(ConvertV4TagsAction::class)->run(
            $user->id,
            $photo->id,
            $v4Tags,
            $pickedUp,
            $customTags
        );

        return response()->json([
            'success' => true,
            'msg' => 'tags-added'
        ]);
    }
}
