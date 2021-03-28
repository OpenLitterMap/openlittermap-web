<?php

namespace App\Listeners\AddTags;

use App\Events\Littercoin\LittercoinMined;
use App\Models\User\User;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateUser
{
    /**
     * Update the users table
     *
     * Combine this with update active team
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        $user->count_correctly_verified += 1;

        if ($user->count_correctly_verified >= 100)
        {
            $user->littercoin_allowance += 1;
            $user->count_correctly_verified = 0;

            event (new LittercoinMined($user->id, '100-images-verified'));
        }

        $user->total_litter += $event->total_count;
        $user->save();
    }
}
