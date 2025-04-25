<?php

namespace App\Events;

use App\Models\Users\User;
use Illuminate\Support\Collection;

class AchievementsUnlocked
{
    public function __construct(User $user, Collection $defs)
    {

    }
}
