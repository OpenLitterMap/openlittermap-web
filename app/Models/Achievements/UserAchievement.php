<?php

namespace App\Models\Achievements;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAchievement extends Pivot
{
    protected $table = 'user_achievements';

    protected $casts = [
        'unlocked_at' => 'datetime',
        'snapshot'    => 'array',
    ];

    public $timestamps = true;
}
