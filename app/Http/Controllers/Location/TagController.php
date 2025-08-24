<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Services\Locations\LocationService;
use Illuminate\Http\Request;

class TagController extends Controller
{
    private LocationService $service;

    public function __construct(LocationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get top tags for a location
     * GET /api/locations/{type}/{id}/tags/top
     */
    public function top(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'dimension' => 'in:objects,categories,materials,brands,custom',
            'limit' => 'integer|min:1|max:100'
        ]);

        $dimension = $validated['dimension'] ?? 'objects';
        $limit = $validated['limit'] ?? 20;

        $data = $this->service->getTopTags($type, $id, $dimension, $limit);

        return response()->json($data);
    }

    /**
     * Get comprehensive tag summary
     * GET /api/locations/{type}/{id}/tags/summary
     */
    public function summary(string $type, int $id)
    {
        $data = $this->service->getTagSummary($type, $id);

        return response()->json($data);
    }

    /**
     * Get tags grouped by category
     * GET /api/locations/{type}/{id}/tags/by-category
     */
    public function byCategory(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'top_per_category' => 'integer|min:1|max:20'
        ]);

        $topPerCategory = $validated['top_per_category'] ?? 5;

        $data = $this->service->getTagsByCategory($type, $id, $topPerCategory);

        return response()->json($data);
    }

    /**
     * Get cleanup/picked-up statistics
     * GET /api/locations/{type}/{id}/tags/cleanup
     */
    public function cleanup(string $type, int $id)
    {
        $data = $this->service->getCleanupStats($type, $id);

        return response()->json($data);
    }

    /**
     * Get trending tags (month over month)
     * GET /api/locations/{type}/{id}/tags/trending
     */
    public function trending(Request $request, string $type, int $id)
    {
        $validated = $request->validate([
            'dimension' => 'in:objects,brands,materials',
            'limit' => 'integer|min:1|max:50'
        ]);

        $dimension = $validated['dimension'] ?? 'objects';
        $limit = $validated['limit'] ?? 10;

        $data = $this->service->getTrendingTags($type, $id, $dimension, $limit);

        return response()->json($data);
    }
}
