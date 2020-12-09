<?php

use Illuminate\Support\Facades\Route;

// Route::get('test', function() {
// 	$user = \App\Models\User\User::first();
// 	return view('emails.update20', compact('user'));
// });

// only turn this on in limited circumstances
// Route::get('sean', 'TotalDataController@getCSV');

Route::get('/', 'HomeController@index');
Route::get('/about', 'HomeController@index');
Route::get('/world', 'HomeController@index');

// Registration
Route::get('/signup', 'HomeController@index');

// Monthly subscription
Route::post('subscribe', 'SubscribersController@create');

/* Stripe Webhooks - excluded from CSRF protection */
Route::post('/stripe/webhook', 'WebhookController@handleWebhook')->name('webhook');
//Route::post('/stripe/customer-created', 'StripeController@create');
//Route::post('/stripe/payment-success', 'StripeController@payment_success');

/* Stripe - API. */
Route::get('/stripe/subscriptions', 'StripeController@subscriptions');
Route::post('/stripe/delete', 'StripeController@delete');
Route::post('/stripe/resubscribe', 'StripeController@resubscribe');

/* Locations */
Route::get('countries', 'MapController@getCountries');
Route::get('states', 'MapController@getStates');
Route::get('cities', 'MapController@getCities');

/* Download data */
Route::post('download', 'DownloadControllerNew@index');

Route::get('/world/{country}', 'HomeController@index');
Route::get('/world/{country}/{state}', 'HomeController@index');
Route::get('/world/{country}/{state}/{city?}/{id?}', 'HomeController@index');
// Route::get('/world/{country}/{city}/city_hex_map', 'MapController@getCity');
// Similarly, get the city and pass the world dynamically
Route::get('/world/{country}/{state}/{city}/map/{minfilter?}/{maxfilter?}/{hex?}', 'HomeController@index');
Route::get('/world/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

// "maps" was used before "world". We will keep this for now to keep old links active.
// Todo - make this dynamic for wildcard routes prefixed by "/{lang}/maps"

Route::group(['middleware' => 'fw-block-blacklisted'], function () {
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

    // new
    Route::get('city', 'MapController@getCity');
});

// Donation page
Route::get('donate', 'HomeController@index');
Route::get('donate/amounts', 'DonateController@index');
Route::post('donate', 'DonateController@submit');

// Get different global data
Route::get('global', 'HomeController@index');
Route::get('global-data', 'MapController@getGlobalData');
Route::get('/global/clusters', 'GlobalMapController@clusters');
Route::get('clusters', 'ClusterController@index');
Route::get('global-points', 'GlobalMapController@index');

/** Auth Routes */
// Get currently auth user when logged in
Route::get('/current-user', 'UsersController@getAuthUser');

// Upload page
Route::get('submit', 'HomeController@index'); // old route
Route::get('upload', 'HomeController@index')->name('upload');

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
Route::get('/settings/presence', 'HomeController@index');
Route::get('/settings/email', 'HomeController@index');
Route::get('/settings/show-flag', 'HomeController@index');
Route::get('/settings/teams', 'HomeController@index');

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
Route::post('settings/avatar', 'UsersController@updateAvatar');

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

// Change default litter presence value
Route::post('/settings/toggle', 'UsersController@togglePresence');

// Toggle Email Subscription
Route::post('/settings/email/toggle', 'EmailSubController@toggleEmailSub');

// Get list of available countries for flag options
Route::get('/settings/flags/countries', 'SettingsController@getCountries');
// Save Country Flag for top 10
Route::post('/settings/save-flag', 'SettingsController@saveFlag');

// Teams
Route::get('/teams', 'HomeController@index');
Route::get('/teams/get-types', 'Teams\TeamsController@types');
Route::get('/teams/combined-effort', 'Teams\TeamsController@combined');
Route::get('/teams/members', 'Teams\TeamsController@members');
Route::get('/teams/joined', 'Teams\TeamsController@joined');

Route::post('/teams/create', 'Teams\TeamsController@create');
Route::post('/teams/join', 'Teams\TeamsController@join');
Route::post('/teams/active', 'Teams\TeamsController@active');


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

/** PAYMENTS */
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
Route::get('/nav', function () {
    return view('pages.navigation');
});

/**
 * ADMIN
 */

Route::group(['prefix' => '/admin'], function () {

    // route
    Route::get('photos', 'HomeController@index');

    // get the data
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
    Route::post('/update-tags', 'AdminController@updateTags');

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
