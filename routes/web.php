<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\SubscribersController;
use App\Http\Controllers\GlobalMap\ClusterController;
use App\Http\Controllers\Uploads\UploadPhotoController;
use App\Http\Controllers\GlobalMap\GlobalMapController;
use App\Http\Controllers\Reports\GenerateImpactReportController;
use App\Http\Controllers\Leaderboard\GetUsersForGlobalLeaderboardController;
use App\Http\Controllers\Leaderboard\GetUsersForLocationLeaderboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/check-auth', function () {
    return response()->json([
        'success' => Auth::check()
    ]);
});

Route::get('impact/{period?}/{year?}/{monthOrWeek?}', GenerateImpactReportController::class);

Route::get('/', HomeController::class);
Route::get('/about', HomeController::class);
Route::get('/world', HomeController::class);
Route::get('/tags', HomeController::class);
Route::get('/community', HomeController::class);
Route::get('/references', HomeController::class);
Route::get('/leaderboard', HomeController::class);
Route::get('/faq', HomeController::class);

Route::get('/community/stats', 'CommunityController@stats');
Route::get('/tags-search', 'DisplayTagsOnMapController@show');

Route::get('/cleanups', HomeController::class);
Route::post('/cleanups/create', 'Cleanups\CreateCleanupController');
Route::get('/cleanups/get-cleanups', 'Cleanups\GetCleanupsGeoJsonController');
Route::get('/cleanups/{inviteLink}/join', HomeController::class);
Route::post('/cleanups/{inviteLink}/join', 'Cleanups\JoinCleanupController');
Route::post('/cleanups/{inviteLink}/leave', 'Cleanups\LeaveCleanupController');

Route::get('/history', HomeController::class);
Route::get('/history/paginated', 'History\GetPaginatedHistoryController');

// Registration
Route::get('/signup', HomeController::class);

// Monthly subscription
Route::post('subscribe', SubscribersController::class);

/* Stripe Webhooks - excluded from CSRF protection */
Route::post('/stripe/webhook', 'WebhookController@handleWebhook')->name('webhook');
//Route::post('/stripe/customer-created', 'StripeController@create');
//Route::post('/stripe/payment-success', 'StripeController@payment_success');

/* Stripe - API. */
Route::get('/stripe/subscriptions', 'StripeController@subscriptions');
Route::post('/stripe/delete', 'StripeController@delete');
Route::post('/stripe/resubscribe', 'StripeController@resubscribe');

/* Locations */
Route::get('location', 'Location\LocationsController@index');

// Route::get('countries', 'Location\LocationsController@getCountries');
Route::get('/countries/names', 'Location\GetListOfCountriesController');
Route::get('/get-world-cup-data', 'WorldCup\GetDataForWorldCupController');

Route::get('states', 'Location\LocationsController@getStates');
Route::get('cities', 'Location\LocationsController@getCities');

/* Download data */
Route::post('download', 'DownloadControllerNew@index');

//Route::get('/world/{country?}', [HomeController::class);
//Route::get('/world/{country}/{state}', [HomeController::class);
Route::get('/world/{country?}/{state?}/{city?}/{id?}', HomeController::class);

// Route::get('/world/{country}/{city}/city_hex_map', 'MapController@getCity');
// Similarly, get the city and pass the world dynamically
Route::get('/world/{country}/{state}/{city}/map/{minfilter?}/{maxfilter?}/{hex?}', HomeController::class);
Route::get('/world/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

// "maps" was used before "world". We will keep this for now to keep old links active.
// Todo - make this dynamic for wildcard routes prefixed by "/{lang}/maps"

Route::group(['middleware' => 'fw-block-blacklisted'], function () {
    // these old routes are deprecated. Need to check if the functions are still in use.
    // Route::get('/maps/{country}', 'Location\LocationsController@getStates');
    // Route::get('/maps/{country}/{state}', 'Location\LocationsController@getCities');
    // Route::get('/maps/{country}/{state}/{city?}/{id?}', 'Location\LocationsController@getCities');
    // Route::get('/maps/{country}/{city}/city_hex_map', 'MapController@getCity');
    // Similarly, get the city and pass the maps dynamically
    // Route::get('/maps/{country}/{state}/{city}/city_hex_map/{minfilter?}/{maxfilter?}/{hex?}', 'MapController@getCity');
    // Route::get('/maps/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

    // new
    Route::get('city', 'MapController@getCity');
});

// Donation page
Route::get('donate', HomeController::class);
Route::get('donate/amounts', 'DonateController@index');
Route::post('donate', 'DonateController@submit');

// Contact page
Route::get('/contact-us', HomeController::class);
Route::post('/contact-us', ContactUsController::class)->name('contact');

// Get data for the Global Map
Route::get('global', HomeController::class);
Route::get('/global/clusters', [ClusterController::class, 'index']);
Route::get('/global/points', [GlobalMapController::class, 'index']);
Route::get('/global/art-data', [GlobalMapController::class, 'artData']);

Route::get('/global/search/custom-tags', 'GlobalMap\Search\FindCustomTagsController');

// Get data for the Global Leaderboard
Route::get('/global/leaderboard', GetUsersForGlobalLeaderboardController::class);
Route::get('/global/leaderboard/location', GetUsersForLocationLeaderboardController::class);

/** Auth Routes */

// Get currently auth user when logged in
Route::get('/current-user', 'UsersController@getAuthUser');

// Upload page
Route::get('upload', HomeController::class)->name('upload');

// Move more authenticated routes into this group instead of applying middleware on controllers
Route::group(['middleware' => 'auth'], function () {
    Route::post('/upload', UploadPhotoController::class);
});

// Tag litter to an image
Route::get('tag', HomeController::class);

// Bulk tag images
Route::get('bulk-tag', HomeController::class);

// The users profile
Route::get('profile', HomeController::class);

// The users upload
Route::get('my-uploads', HomeController::class);

// Get unverified paginated photos for tagging
// Route::get('photos', 'PhotosController@unverified');

// Get the users photos to display links
Route::get('photos/get-my-photos', 'User\Photos\GetMyPhotosController');

Route::post('/profile/upload-profile-photo', 'UsersController@uploadProfilePhoto');

// The user can add tags to image
Route::post('/add-tags', 'PhotosController@addTags');

// The user can change Remaining bool of a photo in Profile
Route::post('/profile/photos/remaining/{id}', 'PhotosController@remaining');

// The user can delete photos
Route::post('/profile/photos/delete', 'PhotosController@deleteImage');

// Paginated array of the users photos (no filters)
Route::get('/user/profile/photos/index', 'User\UserPhotoController@index');

// List of the user's previously added custom tags
Route::get('/user/profile/photos/previous-custom-tags', 'User\UserPhotoController@previousCustomTags');

// Filtered paginated array of the users photos
Route::get('/user/profile/photos/filter', 'User\UserPhotoController@filter');

// Add Many Tags to Many Photos
Route::post('/user/profile/photos/tags/bulkTag', 'User\UserPhotoController@bulkTag');

// Delete selected photos
Route::post('/user/profile/photos/delete', 'User\UserPhotoController@destroy');

/**
 * USER SETTINGS
 */
Route::get('/settings', HomeController::class);
Route::get('/settings/password', HomeController::class);
Route::get('/settings/details', HomeController::class);
Route::get('/settings/social', HomeController::class);
Route::get('/settings/account', HomeController::class);
Route::get('/settings/payments', HomeController::class);
Route::get('/settings/privacy', HomeController::class);
Route::get('/settings/littercoin', HomeController::class);
Route::get('/settings/phone', HomeController::class);
Route::get('/settings/picked-up', HomeController::class);
Route::get('/settings/email', HomeController::class);
Route::get('/settings/show-flag', HomeController::class);
Route::get('/settings/teams', HomeController::class);

// Publicly available Littercoin Page
//Route::get('/littercoin', [HomeController::class);
//Route::get('/littercoin/merchants', [HomeController::class);

// Public Routes
//Route::get('/littercoin-info', 'Littercoin\PublicLittercoinController@getLittercoinInfo');
//Route::post('/add-ada-tx', 'Littercoin\PublicLittercoinController@addAdaTx');
//Route::post('/add-ada-submit-tx', 'Littercoin\PublicLittercoinController@submitAddAdaTx');

// Actions used by Authenticated Littercoin Settings Page
Route::get('/get-users-littercoin', 'Littercoin\LittercoinController@getUsersLittercoin');
Route::post('/wallet-info', 'Littercoin\LittercoinController@getWalletInfo');
Route::post('/littercoin-mint-tx', 'Littercoin\LittercoinController@mintTx');
Route::post('/littercoin-submit-mint-tx', 'Littercoin\LittercoinController@submitMintTx');
Route::post('/littercoin-burn-tx', 'Littercoin\LittercoinController@burnTx');
Route::post('/littercoin-submit-burn-tx', 'Littercoin\LittercoinController@submitBurnTx');
Route::post('/merchant-mint-tx', 'Littercoin\LittercoinController@merchTx');
Route::post('/merchant-submit-mint-tx', 'Littercoin\LittercoinController@submitMerchTx');

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

// Change default litter presence value
Route::post('/settings/toggle', 'UsersController@togglePresence');

// Toggle Email Subscription
Route::post('/settings/email/toggle', 'EmailSubController@toggleEmailSub');

// Get list of available countries for flag options
Route::get('/settings/flags/countries', 'SettingsController@getCountries');
// Save Country Flag for top 10
Route::post('/settings/save-flag', 'SettingsController@saveFlag');
Route::patch('/settings', 'SettingsController@update');

// Teams
Route::get('/teams', HomeController::class);
Route::get('/teams/get-types', 'Teams\TeamsController@types');
Route::get('/teams/data', 'Teams\TeamsDataController@index');
Route::get('/teams/clusters/{team}', 'Teams\TeamsClusterController@clusters');
Route::get('/teams/points/{team}', 'Teams\TeamsClusterController@points');

Route::get('/teams/members', 'Teams\TeamsController@members');
Route::get('/teams/joined', 'Teams\TeamsController@joined');
// Route::get('/teams/map-data', 'Teams\TeamsMapController@index');
Route::get('/teams/leaderboard', 'Teams\TeamsLeaderboardController@index')->middleware('auth');

Route::post('/teams/create', 'Teams\TeamsController@create')->middleware('auth');
Route::post('/teams/update/{team}', 'Teams\TeamsController@update')->middleware('auth');
Route::post('/teams/join', 'Teams\TeamsController@join')->middleware('auth');
Route::post('/teams/leave', 'Teams\TeamsController@leave')->middleware('auth');
Route::post('/teams/active', 'Teams\TeamsController@active')->middleware('auth');
Route::post('/teams/inactivate', 'Teams\TeamsController@inactivateTeam')->middleware('auth');
Route::post('/teams/settings', 'Teams\TeamsSettingsController@index')->middleware('auth');
Route::post('/teams/download', 'Teams\TeamsController@download');
Route::post('/teams/leaderboard/visibility', 'Teams\TeamsLeaderboardController@toggle')->middleware('auth');

// The users profile
Route::get('/user/profile/index', 'User\ProfileController@index');
Route::get('/user/profile/map', 'User\ProfileController@geojson');
Route::get('/user/profile/download', 'User\ProfileController@download');

// Unsubscribe via email (user not authenticated)
Route::get('/emails/unsubscribe/{token}', 'EmailSubController@unsubEmail');
Route::get('/unsubscribe/{token}', 'UsersController@unsubscribeEmail');

Route::get('/terms', function() {
    return view('pages.terms');
});

Route::get('/privacy', function() {
    return view('pages.privacy');
});

// Confirm Email Address, old and new
Route::get('register/confirm/{token}', 'Auth\RegisterController@confirmEmail');
// Route::get('a', function () {
//     $user = \App\Models\User\User::first();
//     return view('auth.emails.confirm', ['user' => $user]);
//  });
Route::get('confirm/email/{token}', 'Auth\RegisterController@confirmEmail')
    ->name('confirm-email-token');

// Logout
Route::get('logout', 'UsersController@logout');

// Register, Login
Auth::routes();

// Overwriting these auth blade views with Vue components
Route::get('/password/reset', [HomeController::class])
    ->middleware('guest');
Route::get('/password/reset/{token}', [HomeController::class])
    ->name('password.reset')
    ->middleware('guest');


/** PAYMENTS */
Route::get('/join/{plan?}', HomeController::class);

Route::get('plans', function () {
    return \App\Plan::all();
});

// Pay
Route::post('/join', 'SubscriptionsController@store');
Route::post('/change', 'SubscriptionsController@change');

// Route::get('/profile/awards', 'AwardsController@getAwards');

///** deprecated */
// * Instructions / navigation
// */
//Route::get('/nav', function () {
//    return view('pages.navigation');
//});

Route::post('/merchants/create', 'Littercoin\Merchants\CreateMerchantController');
Route::get('/merchants/get-geojson', 'Littercoin\Merchants\GetMerchantsGeojsonController');
Route::get('/merchants/get-next-merchant-to-approve', 'Littercoin\Merchants\GetNextMerchantToApproveController');

Route::post('/merchants/upload-photo', 'Merchants\UploadMerchantPhotoController');

/**
 * ADMIN
 */
Route::group(['prefix' => '/admin', 'middleware' => 'admin'], function () {

    // route
    Route::get('photos', HomeController::class);

    Route::get('/find-photo-by-id', 'Admin\FindPhotoByIdController');

    // get the data
    Route::get('get-next-image-to-verify', 'Admin\GetNextImageToVerifyController');
    Route::get('get-countries-with-photos', 'AdminController@getCountriesWithPhotos');

    Route::get('/go-back-one', 'Admin\GoBackOnePhotoController');

    // Get a list of recently registered users
    // Route::get('/users', 'AdminController@getUserCount');
    // Get a list of photos that need to be verified
    // Route::get('/photos', 'AdminController@getPhotos');

    // Verify an image - delete
    Route::post('/verify', 'AdminController@verify');

    // Verify an image - keep
    Route::post('/verify-tags-as-correct', 'Admin\VerifyImageWithTagsController');

    // Remove all tags and reset verification
    Route::post('/reset-tags', 'Admin\AdminResetTagsController');

    // Contents of an image updated, Delete the image
    Route::post('/contentsupdatedelete', 'AdminController@updateDelete');

    // Contents of an image updated, Keep the image
    Route::post('/update-tags', 'Admin\UpdateTagsController');

    // Delete an image and its record
    Route::post('/destroy', 'AdminController@destroy');

    // Merchants
    Route::get('/merchants', HomeController::class);

    Route::post('/merchants/approve', 'Littercoin\Merchants\ApproveMerchantController');
    Route::post('/merchants/delete', 'Littercoin\Merchants\DeleteMerchantController');
});

Route::group(['prefix' => '/bbox', 'middleware' => ['can_bbox']], function () {

    // Add coordinates
    Route::get('/', HomeController::class);

    // Load the next image to add bounding boxes to
    Route::get('/index', 'Bbox\BoundingBoxController@index');

    // Add boxes to image
    Route::post('/create', 'Bbox\BoundingBoxController@create');

    // Mark this image as not bbox compatible
    Route::post('/skip', 'Bbox\BoundingBoxController@skip');

    // Admin - Update the tags
    Route::post('/tags/update', 'Bbox\BoundingBoxController@updateTags');

    // Non-admin - Mark tags as incorrect
    Route::post('/tags/wrong', 'Bbox\BoundingBoxController@wrongTags');

    // Admin - View boxes to verify
    Route::get('/verify', HomeController::class);
    Route::get('/verify/index', 'Bbox\VerifyBoxController@index');
    Route::post('/verify/update', 'Bbox\VerifyBoxController@update');
});
