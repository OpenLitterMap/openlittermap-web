<?php

namespace App\Http\Controllers\Points;

use App\Http\Controllers\Controller;
use App\Http\Requests\Points\PointsStatsRequest;
use App\Services\Points\PointsStatsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class PointsStatsController extends Controller
{
    public function __construct(
        private readonly PointsStatsService $statsService
    ) {}

    /**
     * Get aggregated statistics for the current map view
     * This endpoint uses the same parameters as the points endpoint
     * but returns statistical aggregations instead of individual points
     *
     * @param PointsStatsRequest $request
     * @return array
     */
    public function index(PointsStatsRequest $request): array
    {
        $validated = $request->validated();

        $stats = empty($validated['username'])
            ? Cache::remember($this->buildCacheKey($validated), 120, fn () => $this->statsService->getStats($validated))
            : $this->statsService->getStats($validated);

        return [
            'data' => $stats,
            'meta' => [
                'bbox' => [
                    $validated['bbox']['left'],
                    $validated['bbox']['bottom'],
                    $validated['bbox']['right'],
                    $validated['bbox']['top']
                ],
                'zoom' => $validated['zoom'],
                'categories' => $validated['categories'] ?? null,
                'litter_objects' => $validated['litter_objects'] ?? null,
                'materials' => $validated['materials'] ?? null,
                'brands' => $validated['brands'] ?? null,
                'custom_tags' => $validated['custom_tags'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'username' => $validated['username'] ?? null,
                'year' => $validated['year'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ]
        ];
    }

    private function buildCacheKey(array $params): string
    {
        $key = 'pts:stats:v1:z' . $params['zoom'];
        $key .= ':' . md5(json_encode(Arr::sortRecursive($params)));

        return $key;
    }
}
