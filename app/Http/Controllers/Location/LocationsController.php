<?php

namespace App\Http\Controllers\Location;

use App\Helpers\Get\LoadDataHelper;
use App\Http\Controllers\Controller;

use App\Helpers\Locations;

use Illuminate\Http\Request;

class LocationsController extends Controller
{
    /**
     * Load the data for any location
     *
     * @param int $id
     * @param string $locationType
     */
    public function index ()
    {
        $locationId = request('id');
        $locationType = request('locationType');

        return Locations::getLocation($locationId, $locationType);
    }

    /**
     * Return the main page for the LitterWorldCup
     *
     * @return array
     */
    public static function getCountries ()
    {
        return LoadDataHelper::getCountries();
    }

    /**
     * The States page of the LitterWorldCup has been refreshed
     *
     * @param $country. Should be the name or shortcode of a country.
     *
     * @return array
     */
    public static function getStates ()
    {
        // todo - validate text

        return LoadDataHelper::getStates(request()->country);
    }

    /**
     * The Cities page of the LitterWorldCup has been refreshed
     *
     * @param $country. Should be the name, id, or shortcode of a Country.
     * @param $state. Should be the name or id of a State.
     *
     * @return array
     */
    public static function getCities ()
    {
        // todo - validate text

        return LoadDataHelper::getCities(request()->country, request()->state);
    }
}
