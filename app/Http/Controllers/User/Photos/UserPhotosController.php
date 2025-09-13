<?php

namespace App\Http\Controllers\User\Photos;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPhotosController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $photos = Photo::where('user_id', $user->id)
            ->with([
                // Old relationships
                'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
                'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
                'dogshit', 'art', 'material', 'other', 'customTags',
                // New relationships
                'photoTags.category',
                'photoTags.object',
                'photoTags.primaryCustomTag',
                'photoTags.extraTags.extraTag'
            ])
            ->where('filename', '!=', '/assets/verified.jpg')
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
                'remaining' => $photo->remaining,
                'total_litter' => $photo->total_litter,
                'migrated_at' => $photo->migrated_at,
                'created_at' => $photo->created_at,

                // Old tags structure
                'old_tags' => $photo->tags(), // $this->getOldTags($photo),

                // New tags structure
                'new_tags' => $this->getNewTags($photo),

                // Summary if exists
                'summary' => $photo->summary,
                'xp' => $photo->xp,
                'total_tags' => $photo->total_tags,

                // Migration status
                'is_migrated' => !is_null($photo->migrated_at),
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

    private function getOldTags($photo): array
    {
        $oldTags = [];

        $categories = [
            'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
            'dogshit', 'art', 'material', 'other'
        ];

        foreach ($categories as $category) {
            if ($photo->$category) {
                $categoryData = $photo->$category->toArray();
                // Remove metadata fields
                unset($categoryData['id'], $categoryData['created_at'], $categoryData['updated_at']);

                // Filter out null/zero values
                $filtered = array_filter($categoryData, function($value) {
                    return !is_null($value) && $value !== 0;
                });

                if (!empty($filtered)) {
                    $oldTags[$category] = $filtered;
                }
            }
        }

        // Add custom tags
        if ($photo->customTags && $photo->customTags->count() > 0) {
            $oldTags['custom_tags'] = $photo->customTags->pluck('tag')->toArray();
        }

        return $oldTags;
    }

    private function getNewTags($photo): array
    {
        if (!$photo->photoTags || $photo->photoTags->count() === 0) {
            return [];
        }

        $newTags = [];

        foreach ($photo->photoTags as $photoTag) {
            $tag = [
                'id' => $photoTag->id,
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

            // Add primary custom tag
            if ($photoTag->primaryCustomTag) {
                $tag['primary_custom_tag'] = [
                    'id' => $photoTag->primaryCustomTag->id,
                    'key' => $photoTag->primaryCustomTag->key,
                ];
            }

            // Add extra tags
            $extraTags = [];
            foreach ($photoTag->extraTags as $extra) {
                $extraTag = [
                    'type' => $extra->tag_type,
                    'quantity' => $extra->quantity,
                    'index' => $extra->index,
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

    public function show($photoId)
    {
        $user = Auth::user();

        $photo = Photo::where('user_id', $user->id)
            ->where('id', $photoId)
            ->with([
                'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
                'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
                'dogshit', 'art', 'material', 'other', 'customTags',
                'photoTags.category',
                'photoTags.object',
                'photoTags.primaryCustomTag',
                'photoTags.extraTags.extraTag'
            ])
            ->firstOrFail();

        return response()->json([
            'photo' => [
                'id' => $photo->id,
                'filename' => $photo->filename,
                'datetime' => $photo->datetime,
                'lat' => $photo->lat,
                'lon' => $photo->lon,
                'remaining' => $photo->remaining,
                'total_litter' => $photo->total_litter,
                'migrated_at' => $photo->migrated_at,
                'old_tags' => $photo->tags(),
                'new_tags' => $this->getNewTags($photo),
                'summary' => $photo->summary,
                'xp' => $photo->xp,
                'total_tags' => $photo->total_tags,
                'is_migrated' => !is_null($photo->migrated_at),
            ]
        ]);
    }
}
