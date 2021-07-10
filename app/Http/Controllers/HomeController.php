<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(
 *     title="Homepage API",
 *     version="1.0.0"
 * )
 */
class HomeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/",
     *     description="The main homepage",
     *     @OA\Response(response="default", description="Welcome page")
     * )
     */
    public function index ()
    {
        $auth = Auth::check();

        $user = null;
        if ($auth)
        {
            $user = Auth::user();
            $user->roles;
        }

        // We set this to true when user verifies their email
        $verified = false;
        // or when a user unsubscribes from emails
        $unsub = false;

        return view('root', compact('auth', 'user', 'verified', 'unsub'));
    }
}
