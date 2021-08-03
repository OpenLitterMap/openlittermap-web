<?php

namespace App\Actions\Photos;

use GuzzleHttp\Client;

class ReverseGeocodeLocationAction
{
    /** @var Client */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function run($latitude, $longitude): array
    {
        $apiKey = config('services.location.secret');

        $url = "https://locationiq.org/v1/reverse.php?format=json" .
            "&key=" . $apiKey . "&lat=" . $latitude . "&lon=" . $longitude . "&zoom=20";

        return json_decode(
            $this->client->get($url)->getBody(),
            true
        );
    }
}
