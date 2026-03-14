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
            if (! in_array($operator, ['=', '>', '<'], true)) {
                $operator = '=';
            }
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

        // Picked up filter (per-tag level: true, false, or null=no info)
        if ($request->has('picked_up')) {
            $pickedUp = $request->input('picked_up');
            if ($pickedUp === 'true') {
                $query->whereHas('photoTags', fn($q) => $q->where('picked_up', true));
            } elseif ($pickedUp === 'false') {
                $query->whereHas('photoTags', fn($q) => $q->where('picked_up', false));
            }
        }

        // Country filter
        if ($request->filled('country')) {
            $query->whereHas('countryRelation', fn($q) => $q->where('country', $request->input('country')));
        }

        // State filter
        if ($request->filled('state')) {
            $query->whereHas('stateRelation', fn($q) => $q->where('state', $request->input('state')));
        }

        // City filter
        if ($request->filled('city')) {
            $query->whereHas('cityRelation', fn($q) => $q->where('city', $request->input('city')));
        }

        // Verified status filter
        if ($request->has('verified') && $request->input('verified') !== null) {
            $query->where('verified', (int) $request->input('verified'));
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
                'countryRelation',
                'stateRelation',
                'cityRelation',
                'photoTags.category',
                'photoTags.object',
                'photoTags.type',
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
                'verified' => $photo->verified,
                'country' => $photo->countryRelation?->country,
                'state' => $photo->stateRelation?->state,
                'city' => $photo->cityRelation?->city,
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
                'photoTags.type',
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

        // Total photos for user (exclude verified.jpg placeholder to match index query)
        $baseQuery = Photo::where('user_id', $user->id)
            ->where('filename', '!=', '/assets/verified.jpg');

        $totalPhotos = (clone $baseQuery)->count();

        // Untagged photos count — summary is null until tags are added
        $leftToTag = (clone $baseQuery)
            ->whereNull('summary')
            ->count();

        // Get tag counts from Redis, with DB fallback
        $userMetrics = RedisMetricsCollector::getUserMetrics($user->id);
        $totalTags = array_sum($userMetrics['objects'] ?? [])
            + array_sum($userMetrics['materials'] ?? [])
            + array_sum($userMetrics['brands'] ?? [])
            + array_sum($userMetrics['custom_tags'] ?? []);

        if ($totalTags === 0) {
            $totalTags = (int) (clone $baseQuery)->sum('total_tags');
        }

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
     * Get hierarchical location data for the user's photos
     */
    public function locations(): JsonResponse
    {
        $user = Auth::user();

        $photos = Photo::where('user_id', $user->id)
            ->whereNotNull('country_id')
            ->with(['countryRelation', 'stateRelation', 'cityRelation'])
            ->get();

        $tree = [];

        foreach ($photos as $photo) {
            $country = $photo->countryRelation?->country;
            $state = $photo->stateRelation?->state;
            $city = $photo->cityRelation?->city;

            if (! $country) {
                continue;
            }

            if (! isset($tree[$country])) {
                $tree[$country] = [];
            }

            if ($state && ! isset($tree[$country][$state])) {
                $tree[$country][$state] = [];
            }

            if ($state && $city && ! in_array($city, $tree[$country][$state], true)) {
                $tree[$country][$state][] = $city;
            }
        }

        $locations = [];
        foreach ($tree as $country => $states) {
            $stateList = [];
            foreach ($states as $state => $cities) {
                $stateList[] = [
                    'state' => $state,
                    'cities' => $cities,
                ];
            }
            $locations[] = [
                'country' => $country,
                'states' => $stateList,
            ];
        }

        return response()->json(['locations' => $locations]);
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
            $hasObject = $photoTag->category && $photoTag->object;

            $tag = [
                'id' => $photoTag->id,
                'category_litter_object_id' => $hasObject ? $photoTag->category_litter_object_id : null,
                'litter_object_type_id' => $photoTag->litter_object_type_id,
                'quantity' => $photoTag->quantity,
                'picked_up' => (bool) ($photoTag->picked_up ?? $photo->picked_up),
            ];

            // Add category + object only when both resolve (skip orphaned CLO references)
            if ($hasObject) {
                $tag['category'] = [
                    'id' => $photoTag->category->id,
                    'key' => $photoTag->category->key,
                ];
                $tag['object'] = [
                    'id' => $photoTag->object->id,
                    'key' => $photoTag->object->key,
                ];
            }

            // Add type
            if ($photoTag->type) {
                $tag['type'] = [
                    'id' => $photoTag->type->id,
                    'key' => $photoTag->type->key,
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
