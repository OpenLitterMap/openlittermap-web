<?php

namespace App\Actions\Locations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ReverseGeocodeLocationAction
{
    /** @var Client */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct (Client $client)
    {
        $this->client = $client;
    }

    /**
     * Using the GPS coordinates from the image, return the Reverse Geocode result from OpenStreetMap
     *
     * eg country => Ireland, city => Cork, country_code => ie
     *
     * @param $latitude
     * @param $longitude
     *
     * @return array
     * @throws GuzzleException
     */
    public function run ($latitude, $longitude): array
    {
        $apiKey = config('services.location.secret');

        $url = "https://eu1.locationiq.com/v1/reverse.php?format=json" .
            "&key=" . $apiKey . "&lat=" . $latitude . "&lon=" . $longitude . "&zoom=20";

        return json_decode(
            $this->client->get($url)->getBody(),
            true
        );
    }
}
