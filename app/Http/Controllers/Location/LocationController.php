<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Services\Locations\LocationService;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    private LocationService $service;

    public function __construct(LocationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, string $type = 'country')
    {
        if (!in_array($type, ['country', 'state', 'city'])) {
            return response()->json(['error' => 'Invalid location type'], 400);
        }

        $data = $this->service->getLocations($type, $request->all());

        return response()->json($data);
    }

    public function show(string $type, int $id)
    {
        if (!in_array($type, ['country', 'state', 'city'])) {
            return response()->json(['error' => 'Invalid location type'], 400);
        }

        $data = $this->service->getLocationDetails($type, $id);

        return response()->json($data);
    }

    public function categories(Request $request, string $type, int $id)
    {
        $data = $this->service->getCategoryBreakdown($type, $id);

        return response()->json($data);
    }

    public function timeseries(Request $request, string $type, int $id)
    {
        $period = $request->input('period', 'daily');
        $data = $this->service->getTimeSeries($type, $id, $period);

        return response()->json($data);
    }

    public function leaderboard(Request $request, string $type, int $id)
    {
        $period = $request->input('period', 'all_time');
        $data = $this->service->getLeaderboard($type, $id, $period);

        return response()->json($data);
    }

    public function global()
    {
        $data = $this->service->getGlobalStats();

        return response()->json($data);
    }

    public function worldCup(Request $request)
    {
        $countries = $this->service->getLocations('country', $request->all());
        $global = $this->service->getGlobalStats();

        return response()->json([
            'countries' => $countries['locations'],
            'pagination' => $countries['pagination'],
            'global_stats' => $global,
        ]);
    }
}
