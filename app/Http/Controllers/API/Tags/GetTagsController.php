<?php

namespace App\Http\Controllers\API\Tags;

use App\Models\Materials;
use App\Models\TagType;
use App\Models\Category;
use App\Models\LitterObject;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function index (): JsonResponse
    {
        $tags = Category::select('id', 'key')
            ->with([
                'litterObjects' => function ($q) {
                    // For each LitterObject, also eager-load TagTypes
                    $q->orderBy('key', 'asc')
                        ->with(['tagTypes' => function ($qt) {
                            $qt->orderBy('key', 'asc');
                        }]);
                },
            ])
            ->orderBy('key', 'asc')
            ->get();

        return response()->json([
            'tags' => $tags
        ]);
    }

    //                'litterObjects.materials' => function ($q) {
//                    $q->orderBy('key', 'asc');
//                },
//                'litterObjects.tagTypes.materials' => function ($q) {
//                    $q->orderBy('key', 'asc');
//                }

    /**
     * Search across all tags
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchTags (Request $request): JsonResponse
    {
        $str = $request->input('q');
        $categoryKey = $request->input('category', null);

        $categoryId = null;
        if ($categoryKey) {
            $category = Category::where('key', $categoryKey)->first();
            $categoryId = $category ? $category->id : null;
        }

        $tagTypesQuery = TagType::where('key', 'like', "%$str%");

        if ($categoryId) {
            $tagTypesQuery->where('category_id', $categoryId);
        }

        $litterObjects = LitterObject::where('key', 'like', "%$str%")->get();
        $tagTypes = $tagTypesQuery->get();
        $materials = Materials::where('key', 'like', "%$str%")->get();

        return response()->json([
            'litterObjects' => $litterObjects,
            'tagTypes' => $tagTypes,
            'materials' => $materials
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
    public function getTagTypesForCategoryObject (Category $category, LitterObject $object): JsonResponse
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
     * Get all the Tags for an Object
     *
     * @param LitterObject $object
     * @return JsonResponse
     */
    public function getTagsForObject (LitterObject $object): JsonResponse
    {
        $tags = $object->load([
            'categories',

            'tagTypes' => function ($q) {
                $q->orderBy('key', 'asc');
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
     * Get all the Tags for a TagType
     *
     * @param TagType $tagType
     * @return JsonResponse
     */
    public function getTagsForTagType (TagType $tagType): JsonResponse
    {
        $tags = $tagType->load([
            'litterObjects' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'litterObjects.materials' => function ($q) {
                $q->orderBy('key', 'asc');
            },
            'materials' => function ($q) {
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
