<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQueueController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 50);

        // TODO: Delete all photos from user 5292 then remove this exclusion
        $query = Photo::query()
            ->where('is_public', true)
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->whereNotNull('summary')
            ->where('user_id', '!=', 5292)
            ->whereHas('user', fn ($q) => $q->where('prevent_others_tagging_my_photos', false))
            ->with([
                'user:id,name,username',
                'countryRelation:id,country,shortcode',
                'photoTags.category',
                'photoTags.object',
                'photoTags.extraTags.extraTag',
            ])
            ->when($request->country_id, fn ($q) => $q->where('country_id', $request->country_id))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->photo_id, fn ($q) => $q->where('id', $request->photo_id))
            ->when($request->date_from, fn ($q) => $q->where('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->where('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'asc');

        $paginated = $query->paginate($perPage);

        // Transform photos — build response manually to avoid Location accessor issues
        $photos = $paginated->through(function (Photo $photo) {
            return [
                'id' => $photo->id,
                'user_id' => $photo->user_id,
                'filename' => $photo->filename,
                'country_id' => $photo->country_id,
                'state_id' => $photo->state_id,
                'city_id' => $photo->city_id,
                'verified' => $photo->verified,
                'summary' => $photo->summary,
                'total_tags' => $photo->total_tags,
                'xp' => $photo->xp,
                'created_at' => $photo->created_at,
                'user' => $photo->user ? [
                    'id' => $photo->user->id,
                    'name' => $photo->user->name,
                    'username' => $photo->user->username,
                ] : null,
                'country_relation' => $photo->countryRelation ? [
                    'id' => $photo->countryRelation->id,
                    'country' => $photo->countryRelation->country,
                    'shortcode' => $photo->countryRelation->shortcode,
                ] : null,
                'new_tags' => $this->getNewTags($photo),
            ];
        });

        // Total pending count (unfiltered)
        $totalPending = Photo::query()
            ->where('is_public', true)
            ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
            ->whereNotNull('summary')
            ->count();

        return response()->json([
            'success' => true,
            'photos' => $photos,
            'stats' => [
                'total_pending' => $totalPending,
            ],
        ]);
    }

    /**
     * Transform PhotoTag relationships into the new_tags format.
     *
     * Reuses the pattern from UsersUploadsController::getNewTags().
     */
    private function getNewTags(Photo $photo): array
    {
        if (!$photo->photoTags || $photo->photoTags->count() === 0) {
            return [];
        }

        $newTags = [];

        foreach ($photo->photoTags as $photoTag) {
            $tag = [
                'id' => $photoTag->id,
                'category_litter_object_id' => $photoTag->category_litter_object_id,
                'litter_object_type_id' => $photoTag->litter_object_type_id,
                'quantity' => $photoTag->quantity,
                'picked_up' => $photoTag->picked_up,
            ];

            if ($photoTag->category) {
                $tag['category'] = [
                    'id' => $photoTag->category->id,
                    'key' => $photoTag->category->key,
                ];
            }

            if ($photoTag->object) {
                $tag['object'] = [
                    'id' => $photoTag->object->id,
                    'key' => $photoTag->object->key,
                ];
            }

            $extraTags = [];
            foreach ($photoTag->extraTags as $extra) {
                $extraTag = [
                    'type' => $extra->tag_type,
                    'quantity' => $extra->quantity,
                ];

                if ($extra->extraTag) {
                    $extraTag['tag'] = [
                        'id' => $extra->extraTag->id,
                        'key' => $extra->extraTag->key,
                    ];
                }

                $extraTags[] = $extraTag;
            }

            if (!empty($extraTags)) {
                $tag['extra_tags'] = $extraTags;
            }

            $newTags[] = $tag;
        }

        return $newTags;
    }
}
