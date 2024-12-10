<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Category;
use App\Models\LitterObject;
use App\Models\TagType;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function getAllTags (): JsonResponse
    {
        $categories = Category::with([
            'litterObjects' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ])->orderBy('key', 'asc')->get();

        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * Get all tags for a specific category
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function getTagsForCategory (Category $category): JsonResponse
    {
        $categoryId = $category->id;

        $tags = $category->load([
            'litterObjects' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes' => function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orderBy('key', 'asc');
            },
            'litterObjects.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.tagTypes.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ]);

        return response()->json([
            'tags' => $tags
        ]);
    }

    /**
     * Get all TagTypes & Materials for a LitterObject of a Category.
     *
     * @param Category $category
     * @param LitterObject $object
     * @return JsonResponse
     */
    public function getTagTypesForObject (Category $category, LitterObject $object): JsonResponse
    {
        $categoryId = $category->id;

        $tags = $object->load([
            'tagTypes' => function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                  ->orderBy('key', 'asc');
            },
            'tagTypes.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ]);

        return response()->json([
            'tags' => $tags
        ]);
    }

    /**
     * Get Materials for a LitterObject
     *
     * @param LitterObject $object
     * @return JsonResponse
     */
    public function getMaterialsForObject (LitterObject $object): JsonResponse
    {
        $tags = $object->load([
            'materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ]);

        return response()->json([
            'tags' => $tags
        ]);
    }

    /**
     * Get Materials for a TagType (eg beer_bottle)
     *
     * @param TagType $tagType
     * @return JsonResponse
     */
    public function getMaterialsForTagType (TagType $tagType): JsonResponse
    {
        $tags = $tagType->load([
            'materials' => function ($q) {
                $q->orderBy('key', 'asc');
            }
        ]);

        return response()->json([
            'tags' => $tags
        ]);
    }
}
