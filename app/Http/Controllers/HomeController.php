<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;

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

        if ($auth) $user = Auth::user();

        return view('root', compact('auth', 'user'));
    }
}
