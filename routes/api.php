<?php

use Illuminate\Support\Facades\Route;

// use Image;
use App\User;

use Illuminate\Http\Request;
use App\Events\PhotoVerifiedByAdmin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


///* Our Stripe API */
//Route::group(['prefix' => 'v1', 'middleware' => 'auth:api'], function(){
//
//    Route::get('/user/setup-intent', 'API\UserController@getSetupIntent');
//
//});

/* Mobile App */

// Check if current token is valid
Route::post('/validate-token', function(Request $request) {
    return ['message' => 'valid'];
})->middleware('auth:api');

// Create Account
Route::post('/register', 'ApiRegisterController@register');

// Try to log in
Route::post('/oauth/token', 'AccessTokenController@issueToken');

// Fetch User
Route::get('/user', function (Request $request) {
    return Auth::guard('api')->user();
});

/**
 * Photos
 */

// Upload Photos
Route::post('/photos/submit', 'ApiPhotosController@store')
    ->middleware('auth:api');

// Tag Litter to Photos
Route::post('/photos/update', 'ApiPhotosController@dynamicUpdate')
    ->middleware('auth:api');

// Check for any photos uploaded on web
Route::get('/check-web-photos', 'ApiPhotosController@check')
    ->middleware('auth:api');

Route::post('/add-tags-to-web-img', 'ApiPhotosController@confirm')
    ->middleware('auth:api');

/**
 * Settings
 */
Route::post('/settings/privacy/maps/name', 'ApiSettingsController@mapsName')
    ->middleware('auth:api');

Route::post('/settings/privacy/maps/username', 'ApiSettingsController@mapsUsername')
    ->middleware('auth:api');

Route::post('/settings/privacy/leaderboard/name', 'ApiSettingsController@leaderboardName')
    ->middleware('auth:api');

Route::post('/settings/privacy/leaderboard/username', 'ApiSettingsController@leaderboardUsername')
    ->middleware('auth:api');

Route::post('/settings/privacy/createdby/name', 'ApiSettingsController@createdByName')
    ->middleware('auth:api');

Route::post('/settings/privacy/createdby/username', 'ApiSettingsController@createdByUsername')
    ->middleware('auth:api');

Route::post('/settings/update/{type}', 'ApiSettingsController@update')
    ->middleware('auth:api');

Route::post('/settings/privacy/toggle-previous-tags', 'ApiSettingsController@togglePreviousTags')
    ->middleware('auth:api');
