<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class MobileAppVersionController extends Controller
{
    /**
     * @return \string[][]
     */
    public function __invoke()
    {
        return [
            'ios' => [
                'url' => 'https://apps.apple.com/us/app/openlittermap/id1475982147',
                'version' => '3.2.2'
            ],
            'android' => [
                'url' => 'https://play.google.com/store/apps/details?id=com.geotech.openlittermap',
                'version' => '3.2.2'
            ]
        ];
    }
}
