<?php

namespace App\Http\Controllers\Location;

use App\Models\Location\Country;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class GetListOfCountriesController extends Controller
{
    public function __invoke (): JsonResponse
    {
        $countries = Country::select('id', 'manual_verify', 'country', 'shortcode', 'updated_at')
            ->where('manual_verify', true)
            ->get();

        // don't load these fields
        $countries->makeHidden([
            'brands_data',
            'litter_data',
            'ppm',
            'total_contributors_redis',
            'total_litter_redis',
            'total_photos_redis',
            'total_ppm',
            'updatedAtDiffForHumans',
            'updated_at'
        ]);

        return response()->json([
            'success' => true,
            'countries' => $countries
        ]);
    }
}
