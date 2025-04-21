<?php

namespace App\Services\Redis\Actions;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class AwardGlobalXpService
{
    /**
     * Award XP to a user in global and time‑scoped sorted sets.
     */
    public function run(Photo $photo): void
    {
        $userId = $photo->user_id;
        $xp = 0; // todo : calculate XP for a photo.

        $date = now();
        $year  = $date->format('Y');
        $month = $date->format('m');
        $day   = $date->format('d');

        Redis::pipeline(function ($pipe) use ($userId, $xp, $year, $month, $day) {
            // All‑time user XP
            $pipe->zincrby('users.xp', $xp, $userId);

            // Daily, monthly, yearly user XP
            $pipe->zincrby("users.xp:{$year}-{$month}-{$day}", $xp, $userId);
            $pipe->zincrby("users.xp:{$year}-{$month}",        $xp, $userId);
            $pipe->zincrby("users.xp:{$year}",                 $xp, $userId);
        });
    }
}
