<?php

namespace App\Http\Controllers\API\Tags;

use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterModel;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\TagType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function index (): JsonResponse
    {
        // $rows = LitterModel::with(['category:id,key', 'litterObject:id,key', 'tagType:id,key'])->get();

        // Load all LitterModels with their nested relationships
        $rows = LitterModel::with([
            'category:id,key',
            'litterObject' => function ($q) {
                $q->select('id','key')
                    ->with(['contextualMaterials:id,key']);
            },
            'tagType' => function ($q) {
                $q->select('id','key')
                    ->with(['contextualMaterials:id,key']);
            }
        ])->get();

        $grouped = $rows
            ->groupBy(fn ($row) => $row->category->key)
            ->map(function ($catGroup) {
                // For each category, group by litterObject->key
                return $catGroup->groupBy(fn ($row) => $row->litterObject->key)
                    ->map(function ($objGroup) {
                        // 3) Transform each pivot row => get TagType + Materials
                        return $objGroup->map(function ($r) {
                            // If there's a TagType, collect its materials
                            if ($r->tagType) {
                                return [
                                    'id' => $r->tagType->id,
                                    'key' => $r->tagType->key,
                                    'materials' => $r->tagType->contextualMaterials->pluck('key'),
                                ];
                            } else {
                                // tagType = null => possibly it's an Object-only row
                                // or you can omit this if you're only interested in tagTypes
                                return [
                                    'id' => null,
                                    'key' => null,
                                    'materials' => [],
                                ];
                            }
                        })->values();
                    });
            });


        return response()->json([
            'tags' => $grouped
        ]);
    }

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
