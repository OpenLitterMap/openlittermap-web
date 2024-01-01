<?php

namespace Tests\Doubles\Actions\Locations;

class FakeReverseGeocodingAction
{
    private $address = [
        "house_number" => "10735",
        "road" => "Carlisle Pike",
        "city" => "Latimore Township",
        "county" => "Adams County",
        "state" => "Pennsylvania",
        "postcode" => "17324",
        "country" => "United States of America",
        "country_code" => "us",
        "suburb" => "unknown"
    ];

    private $imageDisplayName = '10735, Carlisle Pike, Latimore Township, Adams County, Pennsylvania, 17324, USA';

    public function run ($latitude, $longitude): array
    {
        return [
            'display_name' => $this->imageDisplayName,
            'address' => $this->address
        ];
    }

    public function withAddress(array $address): FakeReverseGeocodingAction
    {
        $this->address = array_merge($this->address, $address);

        return $this;
    }
}
