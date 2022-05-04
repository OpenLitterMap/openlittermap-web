<?php

namespace App\Helpers\Get;

// Used by LoadingDataHelper
use App\Models\Location\Location;

trait LocationHelper
{
    /**
     * Get the name, username for whoever added this Location
     *
     * We need to check if their settings are set to public.
     *
     * @param Location $location
     *
     * @return Location with name || username || anonymous
     */
    public static function getCreatorInfo (Location $location): Location
    {
        if ($location->creator)
        {
            if ($location->creator->show_name_createdby)
            {
                $location["created_by_name"] = $location->creator->name;
            }
            if ($location->creator->show_username_createdby)
            {
                $location["created_by_username"] = ' @'.$location->creator->username;
            }
        }

        if (empty($location['created_by_name']) && empty($location['created_by_username']))
        {
            $location["created_by_name"] = 'Anonymous';
        }

        return $location;
    }
}
