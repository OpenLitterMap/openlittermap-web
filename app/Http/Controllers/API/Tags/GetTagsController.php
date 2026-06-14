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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

        $litterObjects = LitterObject::with(['categories:id,key'])
            ->whereHas('categories')
            ->select('id', 'key')
            ->orderBy('key')
            ->get()
            ->map(function (LitterObject $obj) use ($objectTypesMap, $objectMaterialsMap) {
                $data = $obj->toArray();
                $data['types'] = $objectTypesMap[$obj->key] ?? [];
                $data['suggested_materials'] = $objectMaterialsMap[$obj->key] ?? [];

                return $data;
            });

        $materials = Materials::select('id', 'key')->orderBy('key')->get();

        $brands = BrandList::select('id', 'key')->orderBy('key')->get();

        $types = LitterObjectType::select('id', 'key', 'name')->orderBy('key')->get();

        $categoryObjects = CategoryObject::select('id', 'category_id', 'litter_object_id')->get();

        $categoryObjectTypes = DB::table('category_object_types')
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
            'tag_usage_counts' => $this->loadTagUsageCounts(),
        ]);
    }

    /**
     * Public-facing "most tagged litter" ranking.
     *
     * Read-only, no auth. Serves the committed public counts file (scoped to
     * photos visible on the map: is_public + verified >= 2) as a ranked list at
     * coarse object + category granularity — the per-type buckets are summed,
     * zero-count pairs hidden, ordered by count descending (ties broken by
     * object then category id for a stable, reproducible ranking). Labels are
     * resolved client-side from the object/category vocabulary /api/tags/all
     * already ships, so only ids and counts are returned here. The read is
     * cached on the file's mtime — never a live aggregate query per request.
     * Missing or malformed files degrade gracefully to an empty list.
     */
    public function getMostTagged(): JsonResponse
    {
        $payload = $this->loadPublicTagCounts();

        $coarse = [];

        foreach ($payload['counts'] as $key => $count) {
            $parts = explode(':', (string) $key);

            if (count($parts) < 2) {
                continue;
            }

            $pairKey = $parts[0].':'.$parts[1];
            $coarse[$pairKey] = ($coarse[$pairKey] ?? 0) + (int) $count;
        }

        $mostTagged = [];

        foreach ($coarse as $pairKey => $count) {
            if ($count <= 0) {
                continue;
            }

            [$objectId, $categoryId] = explode(':', $pairKey);

            $mostTagged[] = [
                'object_id' => (int) $objectId,
                'category_id' => (int) $categoryId,
                'count' => $count,
            ];
        }

        usort($mostTagged, fn (array $a, array $b) => [$b['count'], $a['object_id'], $a['category_id']]
            <=> [$a['count'], $b['object_id'], $b['category_id']]);

        return response()->json([
            'generated_at' => $payload['generated_at'],
            'scope' => $payload['scope'],
            'most_tagged' => $mostTagged,
        ]);
    }

    /**
     * Load the pre-computed public tag counts payload from the committed JSON
     * file, cached on the file's mtime. Missing, empty, or malformed files
     * degrade gracefully to an empty payload.
     *
     * @return array{generated_at: ?string, scope: ?string, counts: array<string, int>}
     */
    protected function loadPublicTagCounts(): array
    {
        $empty = ['generated_at' => null, 'scope' => null, 'counts' => []];

        $path = config('tags.public_counts_path');

        if (! $path || ! File::exists($path)) {
            return $empty;
        }

        $mtime = File::lastModified($path);

        return Cache::rememberForever("tag_counts_public:{$mtime}", function () use ($path, $empty) {
            $decoded = json_decode(File::get($path), true);

            if (! is_array($decoded) || ! isset($decoded['counts']) || ! is_array($decoded['counts'])) {
                return $empty;
            }

            return [
                'generated_at' => $decoded['generated_at'] ?? null,
                'scope' => $decoded['scope'] ?? null,
                'counts' => $decoded['counts'],
            ];
        });
    }

    /**
     * Load the pre-computed tag usage counts map from the committed JSON file.
     *
     * The file is regenerated by hand via `olm:rebuild-tag-counts`. The read is
     * cached on the file's mtime so it happens once until the file changes — we
     * never run a live aggregate query per request. Missing, empty, or malformed
     * files degrade gracefully to an empty map.
     *
     * @return array<string, int>
     */
    protected function loadTagUsageCounts(): array
    {
        $path = config('tags.usage_counts_path');

        if (! $path || ! File::exists($path)) {
            return [];
        }

        $mtime = File::lastModified($path);

        return Cache::rememberForever("tag_usage_counts:{$mtime}", function () use ($path) {
            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) && isset($decoded['counts']) && is_array($decoded['counts'])
                ? $decoded['counts']
                : [];
        });
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
}
