<?php

namespace App\Http\Controllers\Littercoin\Merchants;

use App\Models\Merchant;
use App\Http\Controllers\Controller;
use App\Traits\GeoJson\CreateGeoJsonPoints;

class GetMerchantsGeojsonController extends Controller
{
    use CreateGeoJsonPoints;

    /**
     * Return geojson array of cleanups
     */
    public function __invoke ()
    {
        $merchants = Merchant::with('photos')
            ->whereNotNull('approved')
            ->get();

        $geojson = $this->createGeojsonPoints("Merchants", $merchants);

        return [
            'success' => true,
            'geojson' => $geojson
        ];
    }
}
