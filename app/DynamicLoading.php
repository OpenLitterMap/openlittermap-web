<?php

namespace App;

use Carbon\Carbon;
use Log;

trait DynamicLoading
{
	protected $total_photos = 0;
	protected $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	protected $latlong = [];
	protected $photoCount = 0;

	/**
	 * Who created this resource? Is their name or username public or private?
	 * @return name || username || anonymous
	 */
	protected function getCreatorInfo ($location)
	{
		if ($location->creator)
		{
			if ($location->creator->show_name_createdby == 1)
			{
	        	$location["created_by_name"] = $location->creator->name;
	        }
	        if ($location->creator->show_username_createdby == 1)
	        {
	        	$location["created_by_username"] = ' @'.$location->creator->username;
	        }
	    }

		if (! isset($location->created_by_name))
		{
			if (! isset($location->created_by_username))
			{
				$location["created_by_name"] = 'Anonymous';
			}
		}

		return $location;
	}

	/**
	 * Get the leaderboard for this location 
	 * @return array position, name || username & xp
	 */
	protected function getLeaders ($leaders)
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

            else if ($leader->show_username)
            {
                $username = '@'.$leader->username;
            }

            $arrayOfLeaders[$newIndex] = [
                'position' => $newIndex,
                'name' => $name,
                'username' => $username,
                'xp' => number_format($leader->xp),
                'linkinsta' => $leader->link_instagram
            ];

            $newIndex++;
            if (sizeof($arrayOfLeaders) == 10) break;
    	}

    	return $arrayOfLeaders;
	}

	/**
	 * 
	 */
	protected function getInitialPhotoLatLon ($photoData)
	{
		$lat = (double)$photoData->lat;
		$lon = (double)$photoData->lon;
		$this->latlong[0] = $lat;
		$this->latlong[1] = $lon;
	}
}