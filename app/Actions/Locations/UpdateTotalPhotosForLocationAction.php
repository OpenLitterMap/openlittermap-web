<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class UpdateTotalPhotosForLocationAction
{
    public const KEY = 'total_photos';

    /**
     * Increments or decrements total_photos
     * from a redis hash for each location
     *
     * @param string $countryId Country
     * @param string $stateId State
     * @param string $cityId City
     * @param int $increaseBy can be negative, value will be subtracted, but not below 0
     */
    public function run(string $countryId, string $stateId, string $cityId, int $increaseBy = 1)
    {
        $this->updateValue("country:$countryId", $increaseBy);

        $this->updateValue("state:$stateId", $increaseBy);

        $this->updateValue("city:$cityId", $increaseBy);
    }

    /**
     * Updates the given redis hash
     * Takes into account negative values that will set the counter below 0
     * In which case doesn't apply the update
     *
     * @param $hashName
     * @param $value
     */
    protected function updateValue($hashName, $value)
    {
        // Separate if conditions to skip redis check when $value is positive
        if ($value < 0) {
            if (Redis::hget($hashName, self::KEY) + $value < 0) {
                return;
            }
        }

        Redis::hincrby($hashName, self::KEY, $value);
    }
}
