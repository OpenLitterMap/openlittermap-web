<?php

namespace App\Http\Controllers\API\Tags;

use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterModel;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\TagType;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTagsController extends Controller
{
    /**
     * Get the Tags in their nested structure
     */
    public function index (Request $request): JsonResponse
    {
        [$query, $searchQuery] = $this->generateQueryFromRequest($request);

        $rows = $this->loadRowsFromQuery($query, $searchQuery);

        $grouped = $this->groupTags($rows);

        return response()->json([
            'tags' => $grouped
        ]);
    }

    /**
     * Build a query that filters by available models.
     */
    protected function generateQueryFromRequest (Request $request)
    {
        $categoryKey   = $request['category'] ?? null;
        $objectKey     = $request['object'] ?? null;
        $tagTypeKey    = $request['tag_type'] ?? null;
        $materialsKeys = $request['materials'] ? explode(',', $request['materials']) : null;
        $searchQuery   = $request['search'] ?? null;

        $query = LitterModel::query();

        if ($categoryKey) {
            $query->whereHas('category', function($q) use ($categoryKey) {
                $q->where('key', $categoryKey);
            });
        }

        if ($objectKey) {
            $query->whereHas('litterObject', function($q) use ($objectKey) {
                $q->where('key', $objectKey);
            });
        }

        if ($tagTypeKey) {
            $query->whereHas('tagType', function($q) use ($tagTypeKey) {
                $q->where('key', $tagTypeKey);
            });
        }

        if (!empty($materialsKeys) && $materialsKeys[0] !== '') {
            $query->whereHas('modelMaterials', function ($q) use ($materialsKeys) {
                $q->whereIn('key', $materialsKeys);
            });
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->orWhereHas('category', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"))
                    ->orWhereHas('litterObject', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"))
                    ->orWhereHas('tagType', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"))
                    ->orWhereHas('modelMaterials', fn($subQ) => $subQ->where('key', 'LIKE', "{$searchQuery}%"));
            });
        }

        return [$query, $searchQuery];
    }

    /**
     * Load the data from the generated query.
     * Eager load materials, and filter them by search if it exists.
     */
    protected function loadRowsFromQuery (Builder $query, ?string $searchQuery): Collection
    {
        return $query->with([
            'category:id,key',
            'litterObject' => function ($q) use ($searchQuery) {
                $q->select('id','key')
                    ->with(['materials' => function ($subQ) use ($searchQuery) {
                        if ($searchQuery) {
                            $subQ->where('key', 'LIKE', $searchQuery.'%');
                        }
                    }]);
            },
            'tagType' => function ($q) use ($searchQuery) {
                $q->select('id','key')
                    ->with(['materials' => function ($subQ) use ($searchQuery) {
                        if ($searchQuery) {
                            $subQ->where('key', 'LIKE', $searchQuery.'%');
                        }
                    }]);
            },
            'modelMaterials' => function ($q) use ($searchQuery) {
                if ($searchQuery) {
                    $q->where('key', 'LIKE', $searchQuery.'%');
                }
            }
            ])->get();
    }

    protected function groupTags (Collection $rows): Collection
    {
        return $rows->groupBy(fn($row) => $row->category->key)
            ->map(function ($catGroup) {
                $category = $catGroup->first()->category;

                // For each Category group, group by LitterObject->key
                $litterObjects = $catGroup->groupBy(fn($row) => $row->litterObject->key)
                    ->map(function ($objGroup) {
                        // All rows in objGroup share the same LitterObject
                        $litterObject = $objGroup->first()->litterObject;

                        // Collect TagTypes
                        $tagTypes = $objGroup->map(function ($r) {
                            if ($r->tagType) {
                                return [
                                    'id' => $r->tagType->id,
                                    'key' => $r->tagType->key,
                                    'materials' => $r->modelMaterials ? $r->modelMaterials->pluck('key') : [],
                                ];
                            }

                            return null;
                        })->filter()->values();

                        return [
                            'id' => $litterObject->id,
                            'key' => $litterObject->key,
                            'materials' => $litterObject->materials->pluck('key'),
                            'tag_types' => $tagTypes,
                        ];
                    })
                    ->values();

                return [
                    'id' => $category->id,
                    'key' => $category->key,
                    'litter_objects' => $litterObjects,
                ];
            })
            ->values();
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
