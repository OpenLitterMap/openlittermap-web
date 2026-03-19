<?php

namespace App\Http\Controllers\Points;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Points\PointsRequest;
use App\Models\Photo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PointsController extends Controller
{
    // Constants for configuration
    private const CACHE_TTL = 120; // 2 minutes - shorter for fresher data
    private const MAX_PER_PAGE = 500; // Maximum allowed per page
    private const DEFAULT_PER_PAGE = 1000; // Default page size for gentler payloads
    private const HIGH_ZOOM_LIMIT = 2500; // Safety cap for high zoom
    private const HIGH_ZOOM_PER_PAGE = 250; // Tighter limit at high zoom
    private const BBOX_PRECISION = 5; // ~1.1m precision at equator

    /**
     * Load a Photo by ID
     */
    public function show(int $id)
    {
        $photo = Photo::where('is_public', true)
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings,global_flag',
                'team:id,name,safeguarding',
            ])->findOrFail($id);

        $isSafeguarded = $photo->team && $photo->team->hasSafeguarding();

        return [
            'id' => $photo->id,
            'lat' => $photo->lat,
            'lon' => $photo->lon,
            'datetime' => $photo->datetime,
            'verified' => $photo->verified,
            'filename' => $this->getFilename($photo),
            'username' => $isSafeguarded ? null : ($photo->user && $photo->user->show_username_maps ? $photo->user->username : null),
            'name' => $isSafeguarded ? null : ($photo->user && $photo->user->show_name_maps ? $photo->user->name : null),
            'social' => $isSafeguarded ? null : $photo->user?->social_links,
            'flag' => $isSafeguarded ? null : $photo->user?->global_flag,
            'team' => $photo->team?->name,
            'summary' => $photo->summary,
        ];
    }

    /**
     * Get Paginated Photos within a bounding box & filters.
     *
     * @param PointsRequest $request
     * @return array
     */
    public function index(PointsRequest $request): array
    {
        $validated = $request->validated();

        // Normalize and prepare parameters
        $params = $this->prepareParameters($validated, $request);

        // Check cache for public requests (no username filter)
        if (empty($params['username'])) {
            $cacheKey = $this->buildCacheKey($params);
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($params) {
                return $this->getPhotos($params);
            });
        }

        return $this->getPhotos($params);
    }

    private function prepareParameters(array $validated, $request): array
    {
        // Always include date parameters if present in request
        $params = array_merge(
            $validated,
            $request->only(['from', 'to', 'year'])
        );

        // Normalize bbox for better cache hit rates
        if (isset($params['bbox'])) {
            $params['bbox'] = $this->normalizeBbox($params['bbox']);
        }

        // Apply date normalization (modifies params by reference)
        $this->normalizeDateParams($params);

        // Enforce per_page cap consistently
        if (isset($params['per_page'])) {
            $params['per_page'] = min($params['per_page'], self::MAX_PER_PAGE);
        }

        return $params;
    }

    private function normalizeBbox(array $bbox): array
    {
        $round = fn($v) => round((float)$v, self::BBOX_PRECISION);
        return [
            'left'   => $round($bbox['left']),
            'bottom' => $round($bbox['bottom']),
            'right'  => $round($bbox['right']),
            'top'    => $round($bbox['top']),
        ];
    }

    private function normalizeDateParams(array &$params): void
    {
        // Year takes precedence
        if (!empty($params['year'])) {
            $year = (int) $params['year'];
            $from = Carbon::createFromDate($year, 1, 1, 'UTC')->startOfDay();
            $to = Carbon::createFromDate($year, 12, 31, 'UTC')->endOfDay();

            // Store Carbon instances for query
            $params['_from_carbon'] = $from;
            $params['_to_carbon'] = $to;

            // Store normalized strings for meta
            $params['from'] = $from->toDateString();
            $params['to'] = $to->toDateString();
            return;
        }

        // Normalize from/to dates with UTC timezone
        if (!empty($params['from'])) {
            $from = Carbon::parse($params['from'], 'UTC')->startOfDay();
            $params['_from_carbon'] = $from;
            $params['from'] = $from->toDateString();
        }

        if (!empty($params['to'])) {
            $to = Carbon::parse($params['to'], 'UTC')->endOfDay();
            $params['_to_carbon'] = $to;
            $params['to'] = $to->toDateString();
        }
    }

    private function getPhotos(array $params): array
    {
        $bbox = $params['bbox'];

        $query = Photo::query()
            ->select([
                'photos.id',
                'photos.verified',
                'photos.user_id',
                'photos.team_id',
                'photos.lat',
                'photos.lon',
                'photos.datetime',
                'photos.remaining', // TODO: remove post-migration — use Photo::picked_up accessor
                'photos.summary',
            ])
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings,global_flag',
                'team:id,name,safeguarding'
            ])
            ->where('is_public', true);

        // Filter by bounding box using spatial index (photos_geom_sidx)
        // SRID 4326 axis order: latitude first, longitude second
        // Tiny epsilon expansion (~1m) so ST_Contains includes boundary points,
        // matching the old inclusive whereBetween behavior.
        $e = 0.00001;
        $query->whereRaw(
            'ST_Contains(ST_GeomFromText(?, 4326), geom)',
            [sprintf(
                'POLYGON((%.8F %.8F,%.8F %.8F,%.8F %.8F,%.8F %.8F,%.8F %.8F))',
                $bbox['bottom'] - $e, $bbox['left'] - $e,
                $bbox['bottom'] - $e, $bbox['right'] + $e,
                $bbox['top'] + $e, $bbox['right'] + $e,
                $bbox['top'] + $e, $bbox['left'] - $e,
                $bbox['bottom'] - $e, $bbox['left'] - $e
            )]
        );

        // Apply all filters
        $this->applyFilters($query, $params);

        // Apply date range
        $this->applyDateFilter($query, $params);

        // Apply username filter
        if (!empty($params['username'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('show_username_maps', true)
                    ->where('username', $params['username']);
            });
        }

        // Apply deterministic ordering for stable pagination
        $query->orderByDesc('datetime')->orderBy('id');

        // Get page and per_page from params
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE);

        // For high zoom levels, enforce a hard cap with proper pagination (Option A)
        if (($params['zoom'] ?? 0) >= 19) {
            // Also tighten per_page for snappier responses at high zoom
            $perPage = min($perPage, self::HIGH_ZOOM_PER_PAGE);

            $cap = self::HIGH_ZOOM_LIMIT;
            $offset = ($page - 1) * $perPage;

            if ($offset >= $cap) {
                // Past the cap: return empty page
                $empty = collect();
                $paginator = new LengthAwarePaginator(
                    $empty,
                    0,
                    $perPage,
                    $page
                );
                return $this->formatPaginatedResponse($paginator, $params);
            }

            $take = min($perPage, $cap - $offset);
            $items = (clone $query)->forPage($page, $take)->get();

            // Get actual count but cap at limit (strip ordering for faster count)
            $total = min($cap, (clone $query)->reorder()->count());

            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page
            );

            return $this->formatPaginatedResponse($paginator, $params);
        }

        // Normal pagination path
        $photos = $query->paginate($perPage, ['*'], 'page', $page);
        return $this->formatPaginatedResponse($photos, $params);
    }

    private function applyFilters($query, array $params): void
    {
        $hasFilters = !empty($params['categories']) ||
            !empty($params['litter_objects']) ||
            !empty($params['materials']) ||
            !empty($params['brands']) ||
            !empty($params['custom_tags']);

        if (!$hasFilters) {
            return;
        }

        // Prefetch IDs for performance
        $materialIds = $this->prefetchIds('materials', $params['materials'] ?? []);
        $brandIds = $this->prefetchIds('brandslist', $params['brands'] ?? []);

        // Apply filters with proper grouping
        $query->whereHas('photoTags', function ($pt) use ($params, $materialIds, $brandIds) {
            $pt->where(function ($row) use ($params, $materialIds, $brandIds) {
                // Category/object pairing: both must match on same PhotoTag when both supplied
                if (!empty($params['categories']) && !empty($params['litter_objects'])) {
                    $row->whereHas('category', fn($q) => $q->whereIn('key', $params['categories']))
                        ->whereHas('object', fn($q) => $q->whereIn('key', $params['litter_objects']));
                } else {
                    if (!empty($params['categories'])) {
                        $row->whereHas('category', fn($q) => $q->whereIn('key', $params['categories']));
                    }
                    if (!empty($params['litter_objects'])) {
                        $row->whereHas('object', fn($q) => $q->whereIn('key', $params['litter_objects']));
                    }
                }

                // Extra tags: apply as AND constraints if provided
                if (!empty($materialIds)) {
                    $row->whereHas('extraTags', function ($q) use ($materialIds) {
                        $q->where('tag_type', 'material')
                            ->whereIn('tag_type_id', $materialIds);
                    });
                }

                if (!empty($brandIds)) {
                    $row->whereHas('extraTags', function ($q) use ($brandIds) {
                        $q->where('tag_type', 'brand')
                            ->whereIn('tag_type_id', $brandIds);
                    });
                }

                if (!empty($params['custom_tags'])) {
                    $row->whereHas('extraTags', function ($q) use ($params) {
                        $q->where('tag_type', 'custom_tag')
                            ->whereHas('extraTag', function ($inner) use ($params) {
                                $inner->whereIn('key', $params['custom_tags'])->where('approved', true);
                            });
                    });
                }
            });
        });
    }

    private function prefetchIds(string $table, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        return DB::table($table)
            ->whereIn('key', $keys)
            ->pluck('id')
            ->all();
    }

    private function applyDateFilter($query, array $params): void
    {
        // Use normalized Carbon instances if available
        $from = $params['_from_carbon'] ?? null;
        $to = $params['_to_carbon'] ?? null;

        if ($from && $to) {
            $query->whereBetween('datetime', [$from, $to]);
        } elseif ($from) {
            $query->where('datetime', '>=', $from);
        } elseif ($to) {
            $query->where('datetime', '<=', $to);
        }
    }

    private function formatPaginatedResponse($photos, array $params): array
    {
        $features = $this->formatFeatures($photos);

        // Return pagination data at root level (backward compatibility)
        // TODO: Deprecate root-level pagination in v2, prefer meta.pagination
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'page' => $photos->currentPage(),
            'last_page' => $photos->lastPage(),
            'per_page' => $photos->perPage(),
            'total' => $photos->total(),
            'from' => $photos->firstItem(),
            'to' => $photos->lastItem(),
            'has_more_pages' => $photos->hasMorePages(),
            'meta' => $this->buildMetadata($params, [
                'page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
                'from' => $photos->firstItem(),
                'to' => $photos->lastItem(),
                'has_more_pages' => $photos->hasMorePages(),
            ])
        ];
    }

    private function formatFeatures($photos)
    {
        // Handle both paginator and collection
        $items = $photos instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $photos->getCollection()
            : $photos;

        return $items->map(function ($photo) {
            $properties = [
                'id' => $photo->id,
                'datetime' => $photo->datetime,
                'verified' => $photo->verified,
                'picked_up' => $photo->picked_up,
                'summary' => $photo->summary,
                'username' => $photo->user && $photo->user->show_username_maps
                    ? $photo->user->username : null,
                'name' => $photo->user && $photo->user->show_name_maps
                    ? $photo->user->name : null,
                'team' => $photo->team ? $photo->team->name : null,
            ];

            // Add social links and flag if user exists
            if ($photo->user) {
                $properties['social'] = $photo->user->social_links;
                $properties['flag'] = $photo->user->global_flag;
            }

            // Safeguard school team photos — hide student identity on global map
            if ($photo->team && $photo->team->hasSafeguarding()) {
                $properties['name'] = null;
                $properties['username'] = null;
                $properties['social'] = null;
                $properties['flag'] = null;
            }

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$photo->lon, (float)$photo->lat]
                ],
                'properties' => $properties
            ];
        });
    }

    private function buildMetadata(array $params, array $paginationData)
    {
        return [
            'bbox' => [
                $params['bbox']['left'],
                $params['bbox']['bottom'],
                $params['bbox']['right'],
                $params['bbox']['top']
            ],
            'zoom' => $params['zoom'],
            'categories' => $params['categories'] ?? null,
            'litter_objects' => $params['litter_objects'] ?? null,
            'materials' => $params['materials'] ?? null,
            'brands' => $params['brands'] ?? null,
            'custom_tags' => $params['custom_tags'] ?? null,
            'from' => $params['from'] ?? null,  // Date from
            'to' => $params['to'] ?? null,      // Date to
            'username' => $params['username'] ?? null,
            'year' => $params['year'] ?? null,
            'generated_at' => now()->toIso8601String(),
            // Pagination data
            'page' => $paginationData['page'],
            'last_page' => $paginationData['last_page'],
            'per_page' => $paginationData['per_page'],
            'total' => $paginationData['total'],
            'from_item' => $paginationData['from'],  // Renamed to avoid collision
            'to_item' => $paginationData['to'],      // Renamed to avoid collision
            'has_more_pages' => $paginationData['has_more_pages'],
        ];
    }

    private function getFilename($photo)
    {
        // Only show actual filename if photo is verified (level 2 or higher)
        if ($photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value) {
            return $photo->filename;
        }

        // For unverified photos, always show waiting image
        return '/assets/images/waiting.png';
    }

    private function buildCacheKey(array $params): string
    {
        // Clean internal params before building key
        $cleanParams = $params;
        unset($cleanParams['_from_carbon'], $cleanParams['_to_carbon']);

        $key = 'pts:v1:';
        $key .= 'z' . $params['zoom'];

        // Sort parameters for consistent cache keys
        $sortedParams = Arr::sortRecursive($cleanParams);

        // Create a hash of all parameters including normalized bbox and dates
        $key .= ':' . md5(json_encode($sortedParams));

        return $key;
    }
}
