<?php

namespace App;

trait DynamicLoading
{
	protected $total_photos = 0;
	protected $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	protected $latlong = [];
	protected $photoCount = 0;

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
