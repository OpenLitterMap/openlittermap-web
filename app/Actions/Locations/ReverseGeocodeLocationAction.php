<?php

namespace App\Actions\Locations;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class ReverseGeocodeLocationAction
{
    private Client $client;

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
     * @param $latitude
     * @param $longitude
     *
     * @return array
     * @throws GuzzleException
     */
    /**
     * Reverse geocode coordinates via LocationIQ, with a 30-day cache
     * keyed on coordinates rounded to 3 decimal places (~111m).
     *
     * @throws GuzzleException
     */
    public function run ($latitude, $longitude): array
    {
        $cacheKey = sprintf('geocode:%s:%s', round($latitude, 3), round($longitude, 3));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($latitude, $longitude) {
            $apiKey = config('services.location.secret');

            $url = "https://eu1.locationiq.com/v1/reverse.php?format=json"
                . "&key=" . $apiKey
                . "&lat=" . $latitude
                . "&lon=" . $longitude
                . "&zoom=20";

            return json_decode($this->client->get($url)->getBody(), true);
        });
    }
}
