<?php

namespace App\Http\Controllers\User\Photos;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersUploadsController extends Controller
{
    /**
     * Get user photos with filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $perPage = min($request->integer('per_page', 8), 100);

        $query = Photo::where('user_id', $user->id)
            ->where('filename', '!=', '/assets/verified.jpg');

        // Tagged/Untagged filter — uses summary column (set by GeneratePhotoSummaryService on tagging)
        if ($request->has('tagged')) {
            $request->boolean('tagged')
                ? $query->whereNotNull('summary')
                : $query->whereNull('summary');
        }

        // ID filter
        if ($request->filled('id')) {
            $id = $request->integer('id');
            $operator = $request->input('id_operator', '=');
            $query->where('id', $operator, $id);
        }

        // Tag filter (search by litter object key)
        if ($request->filled('tag')) {
            $tag = $request->input('tag');
            $query->whereHas('photoTags.object', function($q) use ($tag) {
                $q->where('key', 'like', "%{$tag}%");
            });
        }

        // Custom tag filter (search through extra tags)
        if ($request->filled('custom_tag')) {
            $customTag = $request->input('custom_tag');
            $query->whereHas('photoTags.extraTags', function($q) use ($customTag) {
                $q->where('tag_type', 'custom_tag')
                    ->whereHas('extraTag', function($q2) use ($customTag) {
                        $q2->where('key', 'like', "%{$customTag}%");
                    });
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('datetime', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('datetime', '<=', $request->input('date_to'));
        }

        $photos = $query
            ->with([
                'team',
                'photoTags.category',
                'photoTags.object',
                'photoTags.extraTags.extraTag',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform the data
        $photos->through(function ($photo) {
            return [
                'id' => $photo->id,
                'filename' => $photo->filename,
                'datetime' => $photo->datetime,
                'lat' => $photo->lat,
                'lon' => $photo->lon,
                'model' => $photo->model,
                'picked_up' => $photo->picked_up,
                'remaining' => $photo->remaining, // @deprecated — use picked_up
                'display_name' => $photo->display_name,
                'team_id' => $photo->team_id,
                'team' => $photo->team,
                'created_at' => $photo->created_at,

                'new_tags' => $this->getNewTags($photo),
                'summary' => $photo->summary,
                'xp' => $photo->xp,
                'total_tags' => $photo->total_tags,
            ];
        });

        return response()->json([
            'photos' => $photos->items(),
            'pagination' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Get single photo details
     */
    public function show($photoId): JsonResponse
    {
        $user = Auth::user();

        $photo = Photo::where('user_id', $user->id)
            ->where('id', $photoId)
            ->with([
                'team',
                'photoTags.category',
                'photoTags.object',
                'photoTags.extraTags.extraTag',
            ])
            ->firstOrFail();

        return response()->json([
            'photo' => [
                'id' => $photo->id,
                'filename' => $photo->filename,
                'datetime' => $photo->datetime,
                'lat' => $photo->lat,
                'lon' => $photo->lon,
                'model' => $photo->model,
                'picked_up' => $photo->picked_up,
                'remaining' => $photo->remaining, // @deprecated — use picked_up
                'display_name' => $photo->display_name,
                'team_id' => $photo->team_id,
                'team' => $photo->team,
                'new_tags' => $this->getNewTags($photo),
                'summary' => $photo->summary,
                'xp' => $photo->xp,
                'total_tags' => $photo->total_tags,
            ],
        ]);
    }

    /**
     * Get stats separately (can be cached)
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Total photos for user
        $totalPhotos = Photo::where('user_id', $user->id)->count();

        // Untagged photos count — summary is null until tags are added
        $leftToTag = Photo::where('user_id', $user->id)
            ->whereNull('summary')
            ->count();

        // Get tag counts from Redis
        $userMetrics = RedisMetricsCollector::getUserMetrics($user->id);
        $totalTags = array_sum($userMetrics['objects'] ?? [])
            + array_sum($userMetrics['materials'] ?? [])
            + array_sum($userMetrics['brands'] ?? [])
            + array_sum($userMetrics['custom_tags'] ?? []);

        // Calculate percentage
        $taggedPhotos = max(0, $totalPhotos - $leftToTag);
        $taggedPercentage = $totalPhotos > 0
            ? (int)round(($taggedPhotos / $totalPhotos) * 100)
            : 0;

        return response()->json([
            'totalPhotos' => $totalPhotos,
            'totalTags' => $totalTags,
            'leftToTag' => $leftToTag,
            'taggedPercentage' => $taggedPercentage
        ]);
    }

    /**
     * Transform new tags structure
     */
    private function getNewTags($photo): array
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

            // Add category
            if ($photoTag->category) {
                $tag['category'] = [
                    'id' => $photoTag->category->id,
                    'key' => $photoTag->category->key,
                ];
            }

            // Add object
            if ($photoTag->object) {
                $tag['object'] = [
                    'id' => $photoTag->object->id,
                    'key' => $photoTag->object->key,
                ];
            }

            // Add extra tags
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
