<?php

namespace App\Http\Controllers\Maps;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class PointsController extends Controller
{
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'zoom' => 'required|integer|min:15|max:20',
            'bbox.left' => 'required|numeric|between:-180,180',
            'bbox.bottom' => 'required|numeric|between:-90,90',
            'bbox.right' => 'required|numeric|between:-180,180',
            'bbox.top' => 'required|numeric|between:-90,90',
            'categories' => 'array',
            'categories.*' => 'string|distinct|exists:categories,key',
            'litter_objects' => 'array',
            'litter_objects.*' => 'string|distinct|exists:litter_objects,key',
            'materials' => 'array',
            'materials.*' => 'string|distinct|exists:materials,key',
            'brands' => 'array',
            'brands.*' => 'string|distinct|exists:brandslist,key',
            'custom_tags' => 'array',
            'custom_tags.*' => 'string|distinct|exists:custom_tags_new,key',
            'per_page' => 'integer|min:1|max:500',
            'page' => 'integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'username' => 'string',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ]);

        $this->validateBbox($validated);

        return $this->getPhotos($validated);
    }

    private function validateBbox(array $params): void
    {
        $bbox = $params['bbox'];

        // Validate bbox ordering
        if ($bbox['left'] >= $bbox['right'] || $bbox['bottom'] >= $bbox['top']) {
            abort(422, 'Invalid bounding box: left must be < right and bottom must be < top');
        }

        // Validate bbox size based on zoom level
        $width = $bbox['right'] - $bbox['left'];
        $height = $bbox['top'] - $bbox['bottom'];
        $area = $width * $height;

        $maxAreas = [
            15 => 100,   // 10° x 10°
            16 => 25,    // 5° x 5°
            17 => 10,    // ~3° x 3°
            18 => 4,     // 2° x 2°
            19 => 1,     // 1° x 1°
            20 => 0.25   // 0.5° x 0.5°
        ];

        if ($area > $maxAreas[$params['zoom']]) {
            abort(422, 'Bounding box too large for zoom level ' . $params['zoom']);
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
                'photos.filename',
                'photos.lat',
                'photos.lon',
                'photos.datetime',
                'photos.remaining',
                'photos.total_litter',
                'photos.summary'
            ])
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps',
                'team:id,name'
            ]);

        // Apply spatial filter using the spatial index
        $query->whereRaw(
            "MBRContains(ST_GeomFromText(?, 4326, 'axis-order=long-lat'), photos.geom)",
            [sprintf('POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
                $bbox['left'], $bbox['bottom'],
                $bbox['right'], $bbox['bottom'],
                $bbox['right'], $bbox['top'],
                $bbox['left'], $bbox['top'],
                $bbox['left'], $bbox['bottom']
            )]
        );

        // Apply all filters
        $this->applyFilters($query, $params);

        // Apply date range (including year filter)
        $this->applyDateFilter($query, $params);

        // Apply username filter
        if (!empty($params['username'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('show_username_maps', true)
                    ->where('username', $params['username']);
            });
        }

        // Apply deterministic ordering for stable pagination
        // Order by datetime descending (newest first), then by id for consistency
        $query->orderByDesc('datetime')->orderByDesc('id');

        // For high zoom levels, consider returning all results without pagination
        if ($params['zoom'] >= 19) {
            $photos = $query->get();
            return $this->formatCollectionResponse($photos, $params);
        }

        // Paginate for lower zoom levels
        $perPage = $params['per_page'] ?? 300;
        $photos = $query->paginate($perPage);

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

        // Apply filters - each filter type requires the photo to have matching PhotoTags
        $query->whereHas('photoTags', function ($pt) use ($params) {
            $pt->where(function ($q) use ($params) {
                // If we have both categories AND objects, they must be in the same PhotoTag
                if (!empty($params['categories']) && !empty($params['litter_objects'])) {
                    $q->whereHas('category', function ($cat) use ($params) {
                        $cat->whereIn('key', $params['categories']);
                    })->whereHas('object', function ($obj) use ($params) {
                        $obj->whereIn('key', $params['litter_objects']);
                    });
                }
                // Otherwise handle them separately
                else {
                    if (!empty($params['categories'])) {
                        $q->orWhereHas('category', function ($cat) use ($params) {
                            $cat->whereIn('key', $params['categories']);
                        });
                    }

                    if (!empty($params['litter_objects'])) {
                        $q->orWhereHas('object', function ($obj) use ($params) {
                            $obj->whereIn('key', $params['litter_objects']);
                        });
                    }
                }

                // Handle extra tags (materials, brands)
                if (!empty($params['materials'])) {
                    $q->orWhereHas('extraTags', function ($et) use ($params) {
                        $et->where('tag_type', 'material')
                            ->whereIn('tag_type_id', function ($subquery) use ($params) {
                                $subquery->select('id')
                                    ->from('materials')
                                    ->whereIn('key', $params['materials']);
                            });
                    });
                }

                if (!empty($params['brands'])) {
                    $q->orWhereHas('extraTags', function ($et) use ($params) {
                        $et->where('tag_type', 'brand')
                            ->whereIn('tag_type_id', function ($subquery) use ($params) {
                                $subquery->select('id')
                                    ->from('brandslist')
                                    ->whereIn('key', $params['brands']);
                            });
                    });
                }

                // Custom tags
                if (!empty($params['custom_tags'])) {
                    $q->orWhereHas('primaryCustomTag', function ($ct) use ($params) {
                        $ct->whereIn('key', $params['custom_tags'])
                            ->where('approved', true);
                    });
                }
            });
        });
    }

    private function applyDateFilter($query, array $params): void
    {
        // Handle year filter
        if (!empty($params['year'])) {
            $year = $params['year'];
            $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfYear();

            $query->whereBetween('datetime', [$startOfYear, $endOfYear]);
            return;
        }

        // Handle date range filters
        if (empty($params['from']) && empty($params['to'])) {
            return;
        }

        $from = $params['from'] ? Carbon::parse($params['from'])->startOfDay() : null;
        $to = $params['to'] ? Carbon::parse($params['to'])->endOfDay() : null;

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

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => $this->buildMetadata($params, [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
                'from' => $photos->firstItem(),
                'to' => $photos->lastItem(),
                'has_more_pages' => $photos->hasMorePages(),
            ])
        ];
    }

    private function formatCollectionResponse($photos, array $params): array
    {
        $features = $this->formatFeatures($photos);

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => $this->buildMetadata($params, [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($photos),
                'total' => count($photos),
                'from' => 1,
                'to' => count($photos),
                'has_more_pages' => false,
            ])
        ];
    }

    private function formatFeatures($photos)
    {
        return $photos->map(function ($photo) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$photo->lon, (float)$photo->lat]
                ],
                'properties' => [
                    'id' => $photo->id,
                    'datetime' => $photo->datetime,
                    'verified' => $photo->verified,
                    'picked_up' => !$photo->remaining,
                    'total_litter' => $photo->total_litter,
                    'filename' => $this->getFilename($photo),
                    'username' => $photo->user && $photo->user->show_username_maps
                        ? $photo->user->username : null,
                    'name' => $photo->user && $photo->user->show_name_maps
                        ? $photo->user->name : null,
                    'team' => $photo->team ? $photo->team->name : null,
                    'summary' => $photo->summary
                ]
            ];
        });
    }

    private function buildMetadata(array $params, array $paginationData)
    {
        return array_merge([
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
            'from' => $params['from'] ?? null,
            'to' => $params['to'] ?? null,
            'username' => $params['username'] ?? null,
            'year' => $params['year'] ?? null,
            'generated_at' => now()->toIso8601String()
        ], $paginationData);
    }

    private function getFilename($photo)
    {
        // Only show actual filename if photo is verified (level 2 or higher)
        if ($photo->verified >= 2) {
            return $photo->filename;
        }

        // For unverified photos, always show waiting image
        return '/assets/images/waiting.png';
    }

    private function buildCacheKey(array $params): string
    {
        $key = 'points:v4:';
        $key .= 'z' . $params['zoom'];

        // Sort parameters for consistent cache keys
        $sortedParams = Arr::sortRecursive($params);

        // Create a hash of all parameters
        $key .= ':' . md5(json_encode($sortedParams));

        return $key;
    }
}
