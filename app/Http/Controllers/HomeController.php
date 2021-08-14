<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * The main homepage
     *
     * @auth bool, logged in or guest
     * @user null, or authenticated user
     */
    public function index ()
    {
        $auth = Auth::check();

        $user = null;

        if ($auth)
        {
            $user = Auth::user();

            // Load this data
            $user->roles;
            $user->settings;
        }

        // We set this to true when user verifies their email
        $verified = false;
        // We set this to true when a user unsubscribes from communication
        $unsub = false;

        return view('root', compact('auth', 'user', 'verified', 'unsub'));
    }
}
