<?php

namespace App\Helpers\Get;

// Used by LoadingDataHelper
use App\Models\Location\Location;

trait LocationHelper
{
    /**
     * Get the name, username for whoever added this Location
     * or whoever was the last to upload
     *
     * We need to check if their settings are set to public.
     *
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

        if ($location->lastUploader)
        {
            if ($location->lastUploader->show_name_createdby)
            {
                $location["last_uploader_name"] = $location->lastUploader->name;
            }

            if ($location->lastUploader->show_username_createdby)
            {
                $location["last_uploader_username"] = '@'.$location->lastUploader->username;
            }
        }

        if (empty($location['last_uploader_name']) && empty($location['last_uploader_username']))
        {
            $location["last_uploader_name"] = 'Anonymous';
        }

        return $location;
    }
}
