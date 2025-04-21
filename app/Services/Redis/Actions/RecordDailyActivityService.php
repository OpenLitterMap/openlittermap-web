<?php

namespace App\Services\Redis\Actions;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class RecordDailyActivityService
{
    /**
     * Record one activity (photo upload) for user and each location for the given photo.
     */
    public function run(Photo $photo): void
    {
        $date  = now()->format('Y-m-d');
        $userId = $photo->user_id;
        $locations = [
            'country' => $photo->country_id,
            'state'   => $photo->state_id,
            'city'    => $photo->city_id,
        ];

        // User daily activity (hash of date->count)
        Redis::hincrby("activity:users:{$userId}", $date, 1);

        // Location daily activity
        foreach ($locations as $type => $id) {
            if (! $id) continue;
            Redis::hincrby("activity:loc:{$type}:{$id}", $date, 1);
        }
    }
}
