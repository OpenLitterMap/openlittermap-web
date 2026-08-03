<?php

namespace App\Http\Controllers\API\Tags;

use App\Enums\CategoryKey;
use App\Http\Controllers\Controller;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\Materials;
use App\Tags\TagsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public function getAllTags(): JsonResponse
    {
        $categories = Category::select('id', 'key')
            ->where('key', '!=', CategoryKey::Unclassified->value)
            ->orderBy('key')
            ->get();

        $objectMaps = TagsConfig::buildObjectMaps('types', 'materials');
        $objectTypesMap = $objectMaps['types'];
        $objectMaterialsMap = $objectMaps['materials'];

        // Only objects reachable through a SELECTABLE pair — see CategoryObject::selectableIds().
        $selectableCloIds = CategoryObject::selectableIds();

        $selectablePairs = CategoryObject::select('id', 'category_id', 'litter_object_id')
            ->whereIn('id', $selectableCloIds)
            ->get();

        // category ids per object, restricted to selectable pairs. The tagging UI builds one
        // searchable entry per (object, category) in obj.categories, so an unfiltered
        // relation would surface a repaired pair (e.g. marine/bag) even though the object
        // itself is only canonical elsewhere (food/bag).
        $allowedCategoryIds = $selectablePairs
            ->groupBy('litter_object_id')
            ->map(fn ($rows) => $rows->pluck('category_id')->all());

        $litterObjects = LitterObject::with(['categories:id,key'])
            ->whereIn('id', $selectablePairs->pluck('litter_object_id')->unique())
            ->select('id', 'key')
            ->orderBy('key')
            ->get()
            ->map(function (LitterObject $obj) use ($objectTypesMap, $objectMaterialsMap, $allowedCategoryIds) {
                $data = $obj->toArray();
                $allowed = $allowedCategoryIds[$obj->id] ?? [];

                $data['categories'] = collect($data['categories'] ?? [])
                    ->filter(fn ($cat) => in_array($cat['id'], $allowed, true))
                    ->values()
                    ->all();

                $data['types'] = $objectTypesMap[$obj->key] ?? [];
                $data['suggested_materials'] = $objectMaterialsMap[$obj->key] ?? [];

                return $data;
            });

        $materials = Materials::select('id', 'key')->orderBy('key')->get();

        $brands = BrandList::select('id', 'key')->orderBy('key')->get();

        $types = LitterObjectType::select('id', 'key', 'name')->orderBy('key')->get();

        $categoryObjects = $selectablePairs;

        $categoryObjectTypes = DB::table('category_object_types')
            ->whereIn('category_litter_object_id', $selectableCloIds)
            ->select('category_litter_object_id', 'litter_object_type_id')
            ->get();

        return response()->json([
            'categories' => $categories,
            'objects' => $litterObjects,
            'materials' => $materials,
            'brands' => $brands,
            'types' => $types,
            'category_objects' => $categoryObjects,
            'category_object_types' => $categoryObjectTypes,
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

        // Selectability is defined by TagsConfig, not by pivot existence. Repairing orphaned
        // tags (olm:repair-tag-pivot) creates pivots for historical pairs — including ones on
        // canonical objects such as marine/bag — which must NOT become taggable.
        $query = CategoryObject::query()->whereIn('id', CategoryObject::selectableIds());

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
}
