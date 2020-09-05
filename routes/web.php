<?php

use Illuminate\Support\Facades\Route;

// Route::get('test', 'TestController@getTest');
// Route::post('test', 'TestController@postTest');

use App\User;

// use App\Photo;
// Route::get('test', function() {
// 	$user = \App\User::first();
// 	return view('emails.update17', compact('user'));
// });

// only turn this on in limited circumstances
// Route::get('sean', 'TotalDataController@getCSV');

Route::get('/', 'HomeController@index');
Route::post('subscribe', 'SubscribersController@create');
Route::get('/about', 'HomeController@index');
Route::get('/world', 'HomeController@index');

// Registration
Route::get('/signup', 'HomeController@index');

/* Stripe Webhooks - excluded from CSRF protection */
Route::post('/stripe/customer-created', 'StripeController@create');
Route::post('/stripe/payment-success', 'StripeController@payment_success');

Route::get('countries', 'MapController@getCountries');
Route::get('states', 'MapController@getStates');
Route::get('cities', 'MapController@getCities');

Route::get('/world/{country}', 'HomeController@index');
Route::get('/world/{country}/{state}', 'HomeController@index');
Route::get('/world/{country}/{state}/{city?}/{id?}', 'HomeController@index');
// Route::get('/world/{country}/{city}/city_hex_map', 'MapController@getCity');
// Similarly, get the city and pass the world dynamically
Route::get('/world/{country}/{state}/{city}/city_hex_map/{minfilter?}/{maxfilter?}/{hex?}', 'HomeController@index');
Route::get('/world/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

// "maps" was used before "world". We will keep this for now to keep old links active.
// Todo - make this dynamic for wildcart routes prefixed by "/{lang}/maps"
Route::get('/maps', 'MapController@getCountries');
Route::get('/maps/{country}/litter', 'MapController@getCountries');
Route::get('/maps/{country}/leaderboard', 'MapController@getCountries');
Route::get('/maps/{country}/time-series', 'MapController@getCountries');
// Route::get('/maps/total/download', 'MapController@getCountries');

Route::get('/maps/{country}', 'MapController@getStates');
Route::get('/maps/{country}/{state}', 'MapController@getCities');
Route::get('/maps/{country}/{state}/{city?}/{id?}', 'MapController@getCities');
// Route::get('/maps/{country}/{city}/city_hex_map', 'MapController@getCity');
// Similarly, get the city and pass the maps dynamically
Route::get('/maps/{country}/{state}/{city}/city_hex_map/{minfilter?}/{maxfilter?}/{hex?}', 'MapController@getCity');
Route::get('/maps/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

// Donation page
Route::get('donate', 'HomeController@index');
Route::get('donate/amounts', 'DonateController@index');
Route::post('donate', 'DonateController@submit');

// Get different global data
Route::get('global', 'HomeController@index');
Route::get('global-data', 'MapController@getGlobalData');

/** Auth Routes */
// Upload page
Route::get('submit', 'HomeController@index'); // old route
Route::get('upload', 'HomeController@index');

// Upload the image, extract lat long, reverse geocode to address
Route::post('submit', 'PhotosController@store');

// Tag litter to an image
Route::get('tag', 'HomeController@index');

// The users profile
Route::get('profile', 'HomeController@index');

// Get unverified paginated photos for tagging
Route::get('photos', 'PhotosController@unverified');

Route::post('/profile/upload-profile-photo', 'UsersController@uploadProfilePhoto');

// The user can add tags to image
Route::post('/add-tags', 'PhotosController@addTags');

// The user can change Remaining bool of a photo in Profile
Route::post('/profile/photos/remaining/{id}', 'PhotosController@remaining');

// The user can delete photos
Route::post('/profile/photos/delete', 'PhotosController@deleteImage');

/**
 * USER SETTINGS
 */
Route::get('/settings', 'HomeController@index');
Route::get('/settings/password', 'HomeController@index');
Route::get('/settings/details', 'HomeController@index');
Route::get('/settings/account', 'HomeController@index');
Route::get('/settings/payments', 'HomeController@index');
Route::get('/settings/privacy', 'HomeController@index');
Route::get('/settings/littercoin', 'HomeController@index');
Route::get('/settings/phone', 'HomeController@index');
Route::get('/settings/teams', 'HomeController@index');
Route::get('/settings/presence', 'HomeController@index');
Route::get('/settings/email', 'HomeController@index');
Route::get('/settings/show-flag', 'HomeController@index');

// Game settings @ SettingsController
// Toggle Presense of a piece of litter
// Route::post('/settings/settings', 'SettingsController@presense');

// Subscription settings @ SubscriptionsController
// Control Current Subscription
Route::post('/settings/payments/cancel', 'SubscriptionsController@destroy');
Route::post('/settings/payments/reactivate', 'SubscriptionsController@resume');

// User settings @ UsersController
// The user can update their name, username and/or email
Route::post('/settings/details', 'UsersController@details');

// Change password
Route::patch('/settings/details/password', 'UsersController@changePassword');

// The user can delete their profile, and all associated records.
// todo - remove user id from redis
Route::post('/settings/delete', 'UsersController@destroy');

// The user can change their Security settings eg name, surname, username visiblity and toggle public profile
Route::post('/settings/security', [
    'uses' => 'UsersController@updateSecurity',
    'as'   => 'profile.settings.security'
]);

// Update the users privacy eg toggle their anonmyity
Route::post('/settings/privacy/update', 'UsersController@togglePrivacy');

// Control Ethereum wallet and Littercoin
Route::post('/settings/littercoin/update', 'BlockchainController@updateWallet');
Route::post('/settings/littercoin/removewallet', 'BlockchainController@removeWallet');

// Update users phone number
Route::post('/settings/phone/submit', 'UsersController@phone');
Route::post('/settings/phone/remove', 'UsersController@removePhone');

// TEAMS
// // Create a new team
// Route::post('/settings/teams/create', 'TeamController@create');

// // Request to join a new team
// Route::post('/settings/teams/request', 'TeamController@request');

// // Get currently active team
// Route::get('/settings/teams/get', 'TeamController@get');

// // Change active team
// Route::post('/settings/teams/change', 'TeamController@change');

// Change default litter presence value
Route::post('/settings/toggle', 'UsersController@togglePresence');

// Toggle Email Subscription
Route::post('/settings/email/toggle', 'EmailSubController@toggleEmailSub');

// Save Country Flag for top 10
Route::post('/settings/save-flag', 'SettingsController@saveFlag');

/**
 * IMAGE VERIFICATION
 */
// The users currently pending images (verification >= 0.1)
// Route::get('/pending', 'VerificationController@getPending');
// Route::get('/verify', 'VerificationController@getVerification');
// Route::post('/verify', 'VerificationController@verify');


// Unsubscribe via email (user not authenticated)
Route::get('/emails/unsubscribe/{token}', 'EmailSubController@unsubEmail');

/** OTHER PAGES THAT SHOULD NOT BE LOCALIZED **/

// use App\Events\UserSignedUp;
// use Illuminate\Support\Facades\Redis;

	// todo: websockets
	// 1. Publish event with redis
	// Redis::publish('test-channel', json_encode($data));
	// 2. Socket.js -> Node.js + Redis subscribes to the event
	// 3. Fire an event

// use App\Team;
// Route::get('/email', function() {
// 	$team = Team::first();
// 	$user = User::first();
// 	// $team = $team->name;
// 	// $leader = User::first()->name;
// 	// $member = User::first()->name;
// 	return view('emails.update5', compact('user'));
// });

// Route::get('littercoin', function() {
// 	return view('pages.littercoin');
// });

// // Hall of Fame
// Route::get('hall', function() {
// 	return view('pages.hall');
// });


Route::get('/unsubscribe/{token}', 'UsersController@unsubscribeEmail');

Route::get('/terms', function() {
    return view('pages.terms');
});

Route::get('/privacy', function() {
    return view('pages.privacy');
});

// Confirm Email Address, old and new
Route::get('register/confirm/{token}', 'Auth\RegisterController@confirmEmail');
Route::get('confirm/email/{token}', 'Auth\RegisterController@confirmEmail');

// Logout
Route::get('logout', 'UsersController@logout');

// Register, Login
Auth::routes();

// test event
// use App\Events\ImageUploaded;
// Route::get('/testing', function() {

// 	// 1. Publish event with Redis
// 	$data = [
// 		'id' => '2',
// 		'total' => '2',
// 		'Address' => [
// 			'city' => 'Dublin',
// 			'country' => 'Ireland'
// 		]
// 	];

// 	// // old way
// 	// Redis::publish('test-channel', );

// 	json_encode($data);
// 	// new way
// 	event(new ImageUploaded($data));

// 	// return 'done';

// 	// 2. Node.js + Redis subscribes to the event
// 	// 3. Use socket.io to emit to all clients

// });

/**
 * PAYMENTS
 */
Route::get('/join/{plan?}', 'HomeController@index');

Route::get('plans', function () {
    return \App\Plan::all();
});

// Pay
Route::post('/join', 'SubscriptionsController@store');
Route::post('/change', 'SubscriptionsController@change');

// Route::get('/profile/awards', 'AwardsController@getAwards');

/**
 * Instructions / navigation
 */
Route::get('/nav', function() {
    return view('pages.navigation');
});

/**
 * ADMIN
 */

Route::group(['prefix' => '/admin'], function() {

    Route::get('/photos', 'AdminController@index');

    Route::get('get-image', 'AdminController@getImage');

    // Get a list of recently registered users
    // Route::get('/users', 'AdminController@getUserCount');
    // Get a list of photos that need to be verified
    // Route::get('/photos', 'AdminController@getPhotos');
    // Verify an image - delete
    Route::post('/verify', 'AdminController@verify');
    // Verify an image - keep
    Route::post('/verifykeepimage', 'AdminController@verifykeepimage');
    // Send the image back to the user
    Route::post('/incorrect', 'AdminController@incorrect');
    // Contents of an image updated, Delete the image
    Route::post('/contentsupdatedelete', 'AdminController@updateDelete');
    // Contents of an image updated, Keep the image
    Route::post('/contentsupdatekeep', 'AdminController@updateKeep');
    // Delete an image and its record
    Route::post('/destroy', 'AdminController@destroy');
    // LTRX
    // Reduce ltrx allowance - succesfull LTRX generation
    Route::post('/ltrxgenerated', 'LTRXController@success');

    // Add coordinates
    Route::get('bbox', 'AdminController@index');

    Route::get('next-bb-image', 'BoundingBoxController@index');
});

/**
 * REPORTING
 */
// New: Reporting
// Route::get('/report', 'ReportsController@get');

// Route::get('/suburb', 'SuburbsController@getSuburb');
// Route::post('/suburb', 'SuburbsController@getSuburb');

// Route::get('/{vue_capture?}', function () {
//     return view('pages.locations.welcome');
// })->where('vue_capture', '[\/\w\.-]*');
