<?php

namespace App\Helpers;

use App\Helpers\Get\LoadingDataHelper;

class Locations
{
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
            return LoadingDataHelper::getStates($locationId);
        }
        else if ($locationType === "state")
        {
            return LoadingDataHelper::getCities($locationId);
        }
        else {
            return 'no location type';
        }
    }
}
