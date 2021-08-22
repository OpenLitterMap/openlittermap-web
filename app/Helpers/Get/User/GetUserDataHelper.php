<?php


namespace App\Helpers\Get\User;


use App\Level;
use App\Models\Photo;
use App\Models\User\User;

trait GetUserDataHelper
{
    /**
     * Get the users current position and other global metadata
     *
     * @param $userXp
     * @param $totalTags
     * @param $totalImages
     * @return array
     */
    public static function get ($userXp, $totalTags, $totalImages)
    {
        // Todo - Store this metadata in another table
        $totalUsers = User::count();

        $usersPosition = User::where('xp', '>', $userXp)->count() + 1;

        // Todo - Store this metadata in Redis
        $totalPhotosAllUsers = Photo::count();
        // Todo - Store this metadata in Redis
        $totalTagsAllUsers = Photo::sum('total_litter'); // this doesn't include brands

        $usersTotalTags = $totalTags;

        $photoPercent = ($totalImages && $totalPhotosAllUsers) ? number_format(($totalImages / $totalPhotosAllUsers), 2) : 0;
        $tagPercent = ($usersTotalTags && $totalTagsAllUsers) ? number_format(($usersTotalTags / $totalTagsAllUsers), 2) : 0;

        // XP needed to reach the next level
        $nextLevelXp = Level::where('xp', '>=', $userXp)->first()->xp;
        $requiredXp = $nextLevelXp - $userXp;

        return [
            'totalUsers'    => $totalUsers,
            'usersPosition' => $usersPosition,
            'tagPercent'    => $tagPercent,
            'photoPercent'  => $photoPercent,
            'requiredXp'    => $requiredXp
        ];
    }
}
