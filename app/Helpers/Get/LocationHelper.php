<?php

namespace App\Helpers\Get;

// Used by LoadingDataHelper
use App\Models\Location\Location;
use Illuminate\Support\Collection;

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

    /**
     * Get the leaderboard for this location
     *
     * @param Collection $leaders
     * @return array position, name / username, xp
     */
    public static function getLeaders (Collection $leaders): array
    {
        return $leaders
            ->take(10)
            ->filter(function ($leader) {
                return $leader->xp_redis > 0;
            })
            ->map(function ($leader) {
                return [
                    'name' => $leader->show_name ? $leader->name : '',
                    'username' => $leader->show_username ? ('@' . $leader->username) : '',
                    'xp' => number_format($leader->xp_redis),
                    'flag' => $leader->global_flag,
                    'social' => !empty($leader->social_links) ? $leader->social_links : null,
                ];
            })
            ->values()
            ->toArray();
    }
}
