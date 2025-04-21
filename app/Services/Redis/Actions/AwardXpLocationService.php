<?php

namespace App\Services\Redis\Actions;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class AwardXpLocationService
{
    /**
     * Award XP for a single photo: global + location leaderboards.
     */
    public function run(Photo $photo): void
    {
        $userId = $photo->user_id;
        $xp = 0; // todo get from summary

        if (! $userId || $xp <= 0) {
            return;
        }

        // Location LBs
        $date = now();
        $year  = $date->format('Y');
        $month = $date->format('m');
        $day   = $date->format('d');

        $locations = [
            'country' => $photo->country_id,
            'state'   => $photo->state_id,
            'city'    => $photo->city_id,
        ];

        Redis::pipeline(function ($pipe) use ($locations, $userId, $xp, $year, $month, $day) {
            foreach ($locations as $type => $id) {
                if (! $id) {
                    continue;
                }
                // All‑time location leaderboard
                $pipe->zincrby("lb:loc:{$type}:{$id}:total",                 $xp, $userId);
                // Daily, monthly, yearly location LBs
                $pipe->zincrby("lb:loc:{$type}:{$id}:{$year}-{$month}-{$day}", $xp, $userId);
                $pipe->zincrby("lb:loc:{$type}:{$id}:{$year}-{$month}",        $xp, $userId);
                $pipe->zincrby("lb:loc:{$type}:{$id}:{$year}",                 $xp, $userId);
            }
        });
    }
}
