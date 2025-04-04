<?php

namespace App\Http\Controllers\API\Tags;

use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\CategoryObject;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     *
     * We will cache this or move to json.
     */
    public function index (Request $request): JsonResponse
    {
        // Get nested data structure
        [$query, $searchQuery] = $this->generateQueryFromRequest($request);

        $rows = $this->loadRowsFromQuery($query, $searchQuery);

        $groupedTags = $this->groupTags($rows);

        return response()->json([
            'tags' => $groupedTags,
        ]);
    }

    /**
     * Get all tags without grouping
     *
     * Ordered alphabetically.
     */
    public function getAllTags (): JsonResponse {
        $categories = Category::select('id', 'key')->orderBy('key')->get();

        $litterObjects = LitterObject::with(['categories:id,key'])
            ->select('id', 'key')
            ->orderBy('key')
            ->get();

        $materials = Materials::select('id', 'key')->orderBy('key')->get();

        $brands = BrandList::select('id', 'key')->orderBy('key')->get();

        return response()->json([
            'categories' => $categories,
            'objects' => $litterObjects,
            'materials' => $materials,
            'brands' => $brands
        ]);
    }

    /**
     * Build a query that filters by available models.
     */
    protected function generateQueryFromRequest (Request $request): array
    {
        $categoryKey   = $request['category'] ?? null;
        $objectKey     = $request['object'] ?? null;
        $materialsKeys = $request['materials'] ? explode(',', $request['materials']) : null;
        $searchQuery   = $request['search'] ?? null;

        $query = CategoryObject::query();

        if ($categoryKey) {
            $query->whereHas('category', function($q) use ($categoryKey) {
                $q->where('key', $categoryKey);
            });
        }

        if ($objectKey) {
            $query->whereHas('litterObject', function($q) use ($objectKey) {
                $q->where('key', $objectKey)
                  ->orWhere('key', 'LIKE', "%{$objectKey}%");
            });
        }

        if (!empty($materialsKeys) && $materialsKeys[0] !== '') {
            $query->whereHas('materials', function ($q) use ($materialsKeys) {
                $q->whereIn('key', $materialsKeys);
            });
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->orWhereHas('category', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"))
                    ->orWhereHas('litterObject', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"))
                    ->orWhereHas('materials', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"));
            });
        }

        return [$query, $searchQuery];
    }

    /**
     * Load the data from the generated query.
     * Eager load materials, and filter them by search if it exists.
     */
    protected function loadRowsFromQuery ($query, ?string $searchQuery): Collection
    {
        return $query->with([
            'category:id,key',
            'litterObject:id,key',
            'materials' => function ($q) use ($searchQuery) {
                if ($searchQuery) {
                    $q->where('key', 'LIKE', $searchQuery.'%');
                }
            },
        ])
        ->get();
    }

    protected function groupTags (Collection $rows): Collection
    {
        return $rows->groupBy(fn($row) => $row->category->key)
            ->map(function (Collection $catGroup) {
                $category = $catGroup->first()->category;

                // Each pivot record represents a unique (Category, LitterObject) pair.
                // Map each to a litter object with its contextual materials.
                $litterObjects = $catGroup->map(function ($row) {
                    return [
                        'id'        => $row->litterObject->id,
                        'key'       => $row->litterObject->key,
                        'materials' => $row->materials->map(function ($material) {
                            return [
                                'id'  => $material->id,
                                'key' => $material->key,
                            ];
                        }),
                    ];
                })->sortBy('key')->values();

                return [
                    'id'             => $category->id,
                    'key'            => $category->key,
                    'litter_objects' => $litterObjects,
                ];
            });
    }

    // OLD CODE PROBABLY USELESS

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
