<?php

namespace App\Listeners\Littercoin;

use App\Events\Littercoin\LittercoinMined;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Littercoin;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\Redis;

class RewardLittercoin implements ShouldQueue
{
    /**
     * Increase the users Littercoin score
     *
     * Reward with Littercoin if criteria met
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        try {
            $key = RedisKeys::user($event->user_id);
            $count = Redis::hincrby(RedisKeys::stats($key), 'littercoin_progress', 1);

            if ($count === 100)
            {
                Littercoin::firstOrCreate([
                    'user_id' => $event->user_id,
                    'photo_id' => $event->photo_id
                ]);

                // Broadcast an event to anyone viewing the global map
                event(new LittercoinMined($event->user_id, '100-images-verified'));

                Redis::hset(RedisKeys::stats($key), 'littercoin_progress', 0);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('RewardLittercoin Redis failed', [
                'user_id' => $event->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
