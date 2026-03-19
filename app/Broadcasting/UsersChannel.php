<?php

namespace App\Broadcasting;

use App\Models\Users\User;

class UsersChannel
{
    /**
     * Create a new channel instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  \App\Models\Users\User  $user
     * @return array|bool
     */
    public function join(User $user)
    {
        //
    }
}
