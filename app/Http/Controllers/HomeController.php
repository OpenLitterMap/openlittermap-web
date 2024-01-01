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
        $user = null;
        $auth = Auth::check();

        if ($auth)
        {
            $user = Auth::user();
            $user->roles;
        }

        // We set this to true when user verifies their email
        $verified = false;
        // or when a user unsubscribes from emails
        $unsub = false;

        return view('root', ['auth' => $auth, 'user' => $user, 'verified' => $verified, 'unsub' => $unsub]);
    }
}
