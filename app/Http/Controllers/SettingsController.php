<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use App\Models\Location\Country;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\User\UpdateSettingsRequest;

class SettingsController extends Controller
{
    /**
     * Return the list of verified countries for Flag options
     * Also include Puerto Rico for Ryan
     */
    public function getCountries ()
    {
        return Country::where('manual_verify', 1)
            ->orWhere('shortcode', 'pr')
            ->orderBy('country', 'asc')
            ->get()
            ->pluck('country', 'shortcode');
    }

    public function presense (Request $request)
    {
    	$photo = Photo::find($request->id);
    	$photo->remaining = !$photo->remaining;
    	$photo->save();

        return response()->json(['success' => true]);
    }

    /**
     *
     */
    public function saveFlag (Request $request)
    {
    	$user = Auth::user();
	    $user->global_flag = $request->country;
	    $user->save();
	    return ['message' => 'success'];
    }

    public function update (UpdateSettingsRequest $request): array
    {
        $user = auth()->user();
        $user->settings($request->validated());

        return ['message' => 'success'];
    }
}
