<?php

namespace App\Helpers;

use App\Helpers\Get\LoadingDataHelper;
use App\Helpers\Post\UploadingPhotosHelper;

class Locations {
    use UploadingPhotosHelper, LoadingDataHelper;

    /**
     * Get the data for a Country, State, or City by id
     *
     * @param $locationId
     * @param $locationType
     * @return array|string
     */
    public static function getLocation ($locationId, $locationType)
    {
        if ($locationType === "country")
        {
            return self::getStates($locationId);
        }
        else if ($locationType === "state")
        {
            return self::getCities($locationId);
        }
        else {
            return 'no location type';
        }
    }
}
