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
     */
    public function index ()
    {
        $auth = Auth::check();

        return view('root', [ 'auth' => $auth ]);
    }
}
