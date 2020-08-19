<?php

namespace App\Http\Controllers;

use Log;
use JWTAuth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class ApiAuthController extends Controller
{
    public function auth(Request $request) {
    	// get user data 
        \Log::info("log in");
    	$credentials = $request->only('email', 'password');
    	// check if user credentials are correct 
    	try {
    		$token = JWTAuth::attempt($credentials);
    		if (!$token) {
    			return response()->json(['error' => 'invalid credentials'], 401);
    		}
    	} catch (JWTException $e) {
    		// internal server error
    		return response()->json(['error' => 'Something went wrong. Please try again.'], 500);
    	}
    	// generate a token 
    	// return token to the frontend 
	  	$user = JWTAuth::toUser($token);

    	return response()->json(['user' => $user], 200);
    }
}
