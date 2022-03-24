<?php

namespace App\Actions;

use App\Models\AdminVerificationLog;
use App\Models\Photo;
use App\Models\User\User;

class LogAdminVerificationAction
{
    /**
     * Stores the action that an admin takes on the photo verification page
     * The stored rewarded_admin_xp can be used to restore admins' xp
     * in case their data is lost and xp needs to be recalculated
     */
    public function run(
        User $admin,
        Photo $photo,
        string $action,
        array $addedTags,
        array $removedTags,
        int $rewardedAdminXp,
        int $removedUserXp
    ) {
        AdminVerificationLog::create([
            'admin_id' => $admin->id,
            'photo_id' => $photo->id,
            'action' => $action,
            'added_tags' => $addedTags,
            'removed_tags' => $removedTags,
            'rewarded_admin_xp' => $rewardedAdminXp,
            'removed_user_xp' => $removedUserXp
        ]);
    }
}
