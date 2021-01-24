<?php

namespace App\Listeners\AddTags;

use App\Models\Photo;
use App\Models\User\User;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateUsers
{
    /**
     * Update the users table
     *
     * Combine this with update active team
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        if ($user->count_correctly_verified == 100)
        {
            $user->littercoin_allowance += 1;
            $user->count_correctly_verified = 0;
        }

        else $user->count_correctly_verified += 1;

        $user->total_verified += 1;
        $user->total_verified_litter += $event->total_count;

        $user->save();
    }
}
