<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Get the total number of users, and the current users position
     *
     * To get the current position, we need to count how many users have more XP than current users
     */
    public function index ()
    {
        $totalUsers = User::count();
        $usersPosition = User::where('xp', '>', auth()->user()->xp)->count() + 1;

        // Todo - Store this metadata in another table
        $userPhotoCount = Photo::where('user_id', auth()->user()->id)->count();
        // Todo - Store this metadata in another table
        $userTagsCount = Photo::where('user_id', auth()->user()->id)->sum('total_litter');

        // Todo - Store this metadata in another table
        $totalPhotosAllUsers = Photo::count();
        // Todo - Store this metadata in another table
        $totalLitterAllUsers = Photo::sum('total_litter');

        $photoPercent = ($userPhotoCount / $totalPhotosAllUsers);
        $tagPercent = ($userTagsCount / $totalLitterAllUsers);



        return [
            'totalUsers' => $totalUsers,
            'usersPosition' => $usersPosition,
            'totalPhotos' => $userPhotoCount,
            'totalTags' => $userTagsCount,
            'tagPercent' => $tagPercent,
            'photoPercent' => $photoPercent
        ];
    }
}
