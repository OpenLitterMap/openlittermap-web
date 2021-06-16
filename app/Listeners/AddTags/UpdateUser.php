<?php

namespace App\Listeners\AddTags;

use App\Events\Littercoin\LittercoinMined;
use App\Models\User\User;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateUser implements ShouldQueue
{
    /**
     * Update the users proof of work
     *
     * Combine this with update active team
     *
     * Todo - Move all incrementing to Redis
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        // Move to redis
        $user->count_correctly_verified += 1;

        if ($user->count_correctly_verified >= 100)
        {
            $user->littercoin_allowance += 1;
            $user->count_correctly_verified = 0;

            event (new LittercoinMined($user->id, '100-images-verified'));
        }

        // We should increment xp here

        $user->save();
    }
}
