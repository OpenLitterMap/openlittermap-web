<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Support\Facades\Auth;

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
                $requestedLat = (float) request('lat');
                $requestedLon = (float) request('lon');
                $correctLat = (float) $latLon->lat;
                $correctLon = (float) $latLon->lon;

                // Use a small tolerance for float comparison (6 decimal places precision)
                $tolerance = 0.000001;

                $latDiffers = abs($requestedLat - $correctLat) > $tolerance;
                $lonDiffers = abs($requestedLon - $correctLon) > $tolerance;

                if ($latDiffers || $lonDiffers) {
                    // Build the corrected query string
                    $query = [
                        'lat'   => $correctLat,
                        'lon'   => $correctLon,
                        'zoom'  => request('zoom'),
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
