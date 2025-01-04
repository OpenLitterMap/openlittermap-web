<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The main homepage
     *
     * @auth bool, logged in or guest
     * @user null, or authenticated user
     */
    public function __invoke ()
    {
        $user = null;
        $auth = Auth::check();

        // If the user is viewing the global map and loading a photo,
        // we need to load the original latLon coordinates from the photoId
        if (request()->path() === 'global' && request()->has('photo')) {
            $latLon = Photo::where('id', request('photo'))->first(['lat', 'lon']);

            // Replace the latLon in the URL with the original photo coordinates
            if ($latLon) {
                $requestedLat = request('lat');
                $requestedLon = request('lon');
                $correctLat = $latLon->lat;
                $correctLon = $latLon->lon;

                if ($requestedLat != $correctLat || $requestedLon != $correctLon) {
                    // Build the corrected query string
                    $query = [
                        'lat'   => $correctLat,
                        'lon'   => $correctLon,
                        'zoom'  => request('zoom'),   // keep zoom if you want
                        'photo' => request('photo'),
                    ];

                    return redirect()->to('/global?' . http_build_query($query));
                }
            }
        }

        if ($auth)
        {
            $user = Auth::user();
            $user->roles;
        }

        // We set this to true when user verifies their email
        $verified = false;
        // or when a user unsubscribes from emails
        $unsub = false;

        return view('app', compact(
            'auth',
            'user',
            'verified',
            'unsub'
        ));
    }
}
