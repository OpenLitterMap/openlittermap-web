<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function presense(Request $request) {
    	$photo = Photo::find($request->id);
    	$photo->remaining = !$photo->remaining;
    	$photo->save();
    }

    /**
     * 
     */
    public function saveFlag(Request $request)
    {
    	$user = Auth::user();
	    $user->global_flag = $request->country;
	    $user->save();
	    return ['message' => 'success'];
    }
}
