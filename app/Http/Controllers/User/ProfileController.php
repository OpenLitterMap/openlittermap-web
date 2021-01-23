<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
        return [
            'total' => User::count(),
            'position' => User::where('xp', '>', auth()->user()->xp)->count() + 1
        ];
    }
}
