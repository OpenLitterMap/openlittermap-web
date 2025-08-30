<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Services\Locations\LocationService;
use App\Http\Requests\Location\IndexRequest;
use App\Http\Requests\Location\TagsRequest;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PaginatedLocationsResource;
use App\Http\Resources\GlobalStatsResource;
use App\Http\Resources\TagsResource;
use App\Enums\LocationType;

/**
 * Shipping only essential endpoints:
 * - index (list with pagination)
 * - show (single location)
 * - global (global stats)
 * - topTags (tag breakdown)
 *
 * Deferred to v1.1: timeseries, leaderboard, summary
 */
class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $service
    ) {}

    /**
     * Helper to validate and get location type
     */
    private function getType(string $type): LocationType
    {
        $locationType = LocationType::try($type);
        abort_if(!$locationType, 400, 'Invalid location type');
        return $locationType;
    }

    /**
     * Get paginated locations
     */
    public function index(IndexRequest $request, string $type = 'country')
    {
        $locationType = $this->getType($type);

        $data = $this->service->getLocations(
            $locationType,
            $request->validated()
        );

        return new PaginatedLocationsResource($data);
    }

    /**
     * Get single location details
     */
    public function show(string $type, int $id)
    {
        $locationType = $this->getType($type);
        abort_if($id < 1, 400, 'Invalid location ID');

        $location = $this->service->getLocationDetails($locationType, $id, []);

        return new LocationResource($location);
    }

    /**
     * Get global statistics
     */
    public function global()
    {
        $stats = $this->service->getGlobalStats();

        return new GlobalStatsResource($stats);
    }

    /**
     * Get top tags for a location
     */
    public function topTags(TagsRequest $request, string $type, int $id)
    {
        $locationType = $this->getType($type);
        abort_if($id < 1, 400, 'Invalid location ID');

        $tags = $this->service->getTopTags(
            $locationType,
            $id,
            $request->getDimension(),
            $request->getLimit()
        );

        return new TagsResource($tags);
    }
}
