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

        if (!isset($location->created_by_name))
        {
            if (!isset($location->created_by_username))
            {
                $location["created_by_name"] = 'Anonymous';
            }
        }

        return $location;
    }

    /**
     * Get the leaderboard for this location
     *
     * @return array position, name / username, xp
     */
    public static function getLeaders ($leaders)
    {
        $newIndex = 0;
        $arrayOfLeaders = [];

        foreach ($leaders as $leader)
        {
            $name = '';
            $username = '';

            if ($leader->show_name)
            {
                $name = $leader->name;
            }
            if ($leader->show_username)
            {
                $username = '@'.$leader->username;
            }

            if ($name || $username)
            {
                $arrayOfLeaders[$newIndex] = [
                    'position' => $newIndex,
                    'name' => $name,
                    'username' => $username,
                    'xp' => number_format($leader->xp),
                    'linkinsta' => $leader->link_instagram
                ];

                $newIndex++;
            }

            if (sizeof($arrayOfLeaders) == 10) break;
        }

        return $arrayOfLeaders;
    }
}
