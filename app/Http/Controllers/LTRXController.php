<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Models\Photo;
use Illuminate\Http\Request;

class LTRXController extends Controller
{
    /**
     * Apply IsAdmin middleware to all of these routes
     */
    public function __construct() {
    	return $this->middleware('admin');
    	parent::__construct();
	}

	/**
	 * An LTRX Token has been generated
	 */
    public function success(Request $request) {
    	$photoId = $request['photoId'];
    	$photo = Photo::find($photoId);
    	$user = User::find($photo->user_id);
    	$user->littercoin_allowance -= 1;
    	$user->save();
    }
}
