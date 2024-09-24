<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class MobileAppVersionController extends Controller
{
    public function __invoke (): array
    {
        return [
            'ios' => [
                'url' => 'https://apps.apple.com/us/app/openlittermap/id1475982147',
                'version' => '6.1.0'
            ],
            'android' => [
                'url' => 'https://play.google.com/store/apps/details?id=com.geotech.openlittermap',
                'version' => '6.1.0'
            ]
        ];
    }
}
