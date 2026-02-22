<?php

namespace App\Actions\Locations;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

readonly class LocationResult
{
    public function __construct(
        public Country $country,
        public State   $state,
        public City    $city,
        public array   $addressArray,
        public string  $displayName,
    ) {}
}
