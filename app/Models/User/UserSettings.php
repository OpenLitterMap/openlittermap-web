<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{
    protected $fillable = [
        'user_id',

        'show_public_profile',
        'public_profile_download_my_data',
        'public_profile_show_map',
        'twitter',
        'instagram',
        'link_username'

// We need to move these columns here from users table
// Some of the column names could be improved
//        'picked_up',
//        'global_flag',
//        'previous_tags',
//        'litter_picked_up',
//        'show_name_maps',
//        'show_username_maps',
//        'show_name_leaderboard',
//        'show_username_leaderboard',
//        'show_name_createdby',
//        'show_username_createdby',
//        'email_sub'
    ];
}
