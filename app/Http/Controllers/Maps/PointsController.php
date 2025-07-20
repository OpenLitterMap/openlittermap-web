<?php

namespace App\Http\Controllers\Maps;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PointsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'zoom' => 'required|integer|min:16|max:20',
            'bbox.left' => 'required|numeric',
            'bbox.bottom' => 'required|numeric',
            'bbox.right' => 'required|numeric',
            'bbox.top' => 'required|numeric',
            'categories' => 'array',
            'categories.*' => 'string',
            'per_page' => 'integer|min:1|max:500',
            'from' => 'date_format:Y-m-d',
            'to' => 'date_format:Y-m-d',
            'username' => 'string'
        ]);

        // Simple caching for public requests
        if (empty($request->username) && $request->zoom <= 17) {
            $cacheKey = $this->buildCacheKey($validated);
            return Cache::remember($cacheKey, 60, function () use ($validated) {
                return $this->getPhotos($validated);
            });
        }

        return $this->getPhotos($validated);
    }

    private function getPhotos(array $params)
    {
        $bbox = $params['bbox'];

        $query = Photo::query()
            ->select([
                'id',
                'verified',
                'user_id',
                'team_id',
                'filename',
                'lat',
                'lon',
                'datetime',
                'remaining'
            ])
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps',
                'team:id,name'
            ]);

        // Apply bounding box filter (critical fix)
        $query->whereBetween('lat', [$bbox['bottom'], $bbox['top']])
            ->whereBetween('lon', [$bbox['left'], $bbox['right']]);

        // Apply category filter if requested
        if (!empty($params['categories'])) {
            $query->whereHas('photoTags.category', function ($q) use ($params) {
                $q->whereIn('key', $params['categories']);
            });
        }

        // Date filtering
        if (!empty($params['from']) || !empty($params['to'])) {
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

        // Username filter
        if (!empty($params['username'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('show_username_maps', true)
                    ->where('username', $params['username']);
            });
        }

        // Paginate results
        $perPage = $params['per_page'] ?? 300;
        $photos = $query->paginate($perPage);

        return $this->formatResponse($photos);
    }

    private function formatResponse($photos)
    {
        $features = $photos->map(function ($photo) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$photo->lon, (float)$photo->lat] // Fixed order
                ],
                'properties' => [
                    'id' => $photo->id,
                    'datetime' => $photo->datetime,
                    'verified' => $photo->verified,
                    'picked_up' => !$photo->remaining, // Computed, not selected
                    'filename' => $this->getFilename($photo),
                    'username' => $photo->user && $photo->user->show_username_maps
                        ? $photo->user->username : null,
                    'name' => $photo->user && $photo->user->show_name_maps
                        ? $photo->user->name : null,
                    'team' => $photo->team ? $photo->team->name : null
                ]
            ];
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'total' => $photos->total(),
                'per_page' => $photos->perPage(),
                'current_page' => $photos->currentPage()
            ]
        ];
    }

    private function getFilename($photo)
    {
        if ($photo->verified >= 2 || ($photo->user && $photo->user->is_trusted)) {
            return $photo->filename;
        }
        return '/assets/images/waiting.png';
    }

    private function buildCacheKey(array $params): string
    {
        $key = 'points:v2:';
        $key .= 'z' . $params['zoom'];
        $key .= ':' . md5(json_encode($params['bbox']));

        if (!empty($params['categories'])) {
            $cats = $params['categories'];
            sort($cats); // Fix: sort array properly
            $key .= ':c' . md5(implode(',', $cats));
        }

        return $key;
    }
}
