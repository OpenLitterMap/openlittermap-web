<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{
    protected $fillable = [
        'user_id',
        'picked_up',
        'global_flag',
        'previous_tags',
        'litter_picked_up',
        'show_name_maps',
        'show_username_maps',
        'show_name_leaderboard',
        'show_username_leaderboard',
        'show_name_createdby',
        'show_username_createdby',
        'email_sub'
    ];
}
