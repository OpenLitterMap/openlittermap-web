<?php

namespace App\Http\Controllers\Points;

use App\Http\Controllers\Controller;
use App\Services\Points\PointsStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PointsStatsController extends Controller
{
    public function __construct(
        private readonly PointsStatsService $statsService
    ) {}

    /**
     * Get aggregated statistics for the current map view
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
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
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'username' => 'string',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ]);

        // Validate bbox ordering and size
        $this->validateBbox($validated);

        // Get stats
        $stats = $this->statsService->getStats($validated);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'zoom' => $validated['zoom'],
                'cached' => !isset($stats['meta']['generated_fresh'])
            ]
        ]);
    }

    /**
     * Get stats for a specific user
     *
     * @param Request $request
     * @param string $username
     * @return JsonResponse
     */
    public function user(Request $request, string $username): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ]);

        // Add username to params
        $validated['username'] = $username;

        // Use a global bbox for user stats
        $validated['bbox'] = [
            'left' => -180,
            'bottom' => -90,
            'right' => 180,
            'top' => 90
        ];
        $validated['zoom'] = 15; // Default zoom for caching purposes

        $stats = $this->statsService->getStats($validated);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'username' => $username,
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Get stats for a specific area/region
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function area(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => 'nullable|string|size:2',
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ]);

        // Convert area to bbox (you'll need to implement this based on your location data)
        $bbox = $this->getAreaBbox($validated);

        $params = array_merge($validated, [
            'bbox' => $bbox,
            'zoom' => 15 // Default zoom for area stats
        ]);

        $stats = $this->statsService->getStats($params);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'area' => array_intersect_key($validated, array_flip(['country_code', 'state_id', 'city_id'])),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Get global stats summary
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function global(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'year' => 'nullable|integer|min:2017|max:' . date('Y')
        ]);

        // Global bbox
        $validated['bbox'] = [
            'left' => -180,
            'bottom' => -90,
            'right' => 180,
            'top' => 90
        ];
        $validated['zoom'] = 10; // Low zoom for global stats

        $stats = $this->statsService->getStats($validated);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'scope' => 'global',
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Validate bbox parameters
     *
     * @param array $params
     * @return void
     */
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

        if (isset($maxAreas[$params['zoom']]) && $area > $maxAreas[$params['zoom']]) {
            abort(422, 'Bounding box too large for zoom level ' . $params['zoom']);
        }
    }

    /**
     * Get bounding box for a specific area
     *
     * @param array $params
     * @return array
     */
    private function getAreaBbox(array $params): array
    {
        // If city is specified
        if (!empty($params['city_id'])) {
            $city = \App\Models\Location\City::find($params['city_id']);
            if ($city) {
                return [
                    'left' => $city->left ?? $city->lon - 0.1,
                    'bottom' => $city->bottom ?? $city->lat - 0.1,
                    'right' => $city->right ?? $city->lon + 0.1,
                    'top' => $city->top ?? $city->lat + 0.1
                ];
            }
        }

        // If state is specified
        if (!empty($params['state_id'])) {
            $state = \App\Models\Location\State::find($params['state_id']);
            if ($state) {
                return [
                    'left' => $state->left ?? -180,
                    'bottom' => $state->bottom ?? -90,
                    'right' => $state->right ?? 180,
                    'top' => $state->top ?? 90
                ];
            }
        }

        // If country is specified
        if (!empty($params['country_code'])) {
            $country = \App\Models\Location\Country::where('code', $params['country_code'])->first();
            if ($country) {
                return [
                    'left' => $country->left ?? -180,
                    'bottom' => $country->bottom ?? -90,
                    'right' => $country->right ?? 180,
                    'top' => $country->top ?? 90
                ];
            }
        }

        // Default to global bbox
        return [
            'left' => -180,
            'bottom' => -90,
            'right' => 180,
            'top' => 90
        ];
    }
}
