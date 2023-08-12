<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use App\Traits\GeoJson\CreateGeoJsonPoints;
use Illuminate\Http\Request;

class GetCleanupsGeoJsonController extends Controller
{
    use CreateGeoJsonPoints;

    /**
     * Return geojson array of cleanups
     */
    public function __invoke ()
    {
        // Only load cleanups where the date is in the future
        // Todo: Load name, username, team of user when its set to public
        $cleanups = Cleanup::with(['users' => function ($q) {
            $q->select('user_id');
        }])
        ->get();

        $geojson = $this->createGeojsonPoints("OLM Cleanups", $cleanups);

        return [
            'success' => true,
            'geojson' => $geojson
        ];
    }
}
