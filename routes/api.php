<?php

use App\Http\Controllers\Achievements\AchievementsController;
use App\Http\Controllers\API\Tags\GetTagsController;
use App\Http\Controllers\API\Tags\PhotoTagsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Clusters\ClusterController;
use App\Http\Controllers\Leaderboard\LeaderboardController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Location\TagController;
use App\Http\Controllers\Points\PointsController;
use App\Http\Controllers\Points\PointsStatsController;
use App\Http\Controllers\RedisDataController;
use App\Http\Controllers\Uploads\UploadPhotoController;
use App\Http\Controllers\User\Photos\UsersUploadsController;
use App\Http\Controllers\WorldCup\GetDataForWorldCupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| v3 — OLM v5 API
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v3', 'middleware' => ['auth:sanctum']], function () {
    Route::post('/upload', UploadPhotoController::class);
    Route::post('/tags', [PhotoTagsController::class, 'store']);
    Route::put('/tags', [PhotoTagsController::class, 'update']);
    Route::get('/user/photos', [UsersUploadsController::class, 'index']);
    Route::get('/user/photos/stats', [UsersUploadsController::class, 'stats']);
});

/*
|--------------------------------------------------------------------------
| Public (no auth)
|--------------------------------------------------------------------------
*/

Route::get('/tags', [GetTagsController::class, 'index']);
Route::get('/tags/all', [GetTagsController::class, 'getAllTags']);
Route::get('/points', [PointsController::class, 'index']);
Route::get('/points/stats', [PointsStatsController::class, 'index']);
Route::get('/points/{id}', [PointsController::class, 'show'])->where('id', '[0-9]+');
Route::get('/global/stats-data', 'API\GlobalStatsController@index');
Route::get('/mobile-app-version', 'API\MobileAppVersionController');
Route::get('/levels', fn () => response()->json(config('levels.thresholds')));

/*
|--------------------------------------------------------------------------
| Locations
|--------------------------------------------------------------------------
*/

Route::get('/locations/global', [LocationController::class, 'global']);
Route::get('/locations/world-cup', GetDataForWorldCupController::class);
Route::get('/locations/{type}', [LocationController::class, 'index']);
Route::get('/locations/{type}/{id}', [LocationController::class, 'show']);
Route::get('/locations/{type}/{id}/categories', [LocationController::class, 'categories']);
Route::get('/locations/{type}/{id}/timeseries', [LocationController::class, 'timeseries']);
Route::get('/locations/{type}/{id}/leaderboard', [LocationController::class, 'leaderboard']);

Route::prefix('locations/{type}/{id}/tags')->group(function () {
    Route::get('/top', [TagController::class, 'top']);
    Route::get('/summary', [TagController::class, 'summary']);
    Route::get('/by-category', [TagController::class, 'byCategory']);
    Route::get('/cleanup', [TagController::class, 'cleanup']);
    Route::get('/trending', [TagController::class, 'trending']);
});

Route::prefix('clusters')->group(function () {
    Route::get('/', [ClusterController::class, 'index']);
    Route::get('/zoom-levels', [ClusterController::class, 'zoomLevels']);
});

// Legacy location routes (v1)
Route::prefix('v1')->group(function () {
    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/{type}/{id}', [LocationController::class, 'show'])
        ->where('type', 'country|state|city')
        ->where('id', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [RegisterController::class, 'register']);
Route::post('/register', [RegisterController::class, 'register']); // legacy mobile
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:3,1');
Route::post('/password/validate-token', [ResetPasswordController::class, 'validateToken']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);

Route::post('/auth/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])
    ->middleware(app()->isLocal() ? ['web'] : ['web', 'throttle:5,1']);

Route::post('/auth/token', [App\Http\Controllers\Auth\AuthTokenController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/auth/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])
    ->middleware(['web', 'auth:web']);

Route::post('/validate-token', function (Request $request) {
    return ['message' => 'valid'];
})->middleware('auth:sanctum');

// Deprecated routes removed:
// GET /api/user — use GET /api/user/profile/index
// GET /api/current-user — use GET /api/user/profile/index
// POST /api/photos/submit — use POST /api/v3/upload
// POST /api/photos/submit-with-tags — use POST /api/v3/upload + POST /api/v3/tags
// POST /api/photos/upload-with-tags — duplicate of above
// POST /api/photos/upload/with-or-without-tags — duplicate of above
// GET /api/check-web-photos — orphan
// DELETE /api/photos/delete — use POST /api/profile/photos/delete
// POST /api/upload — use POST /api/v3/upload
// POST /api/add-tags — use POST /api/v3/tags
// GET /api/v2/photos/web/index — use GET /api/v3/user/photos
// GET /api/v2/photos/get-untagged-uploads — use GET /api/v3/user/photos?tagged=false
// GET /api/v2/photos/web/load-more — use GET /api/v3/user/photos
// POST /api/v2/add-tags-to-uploaded-image — use POST /api/v3/tags

/*
|--------------------------------------------------------------------------
| User Profile & Photos (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::get('/user/profile/{id}', 'User\ProfileController@show')->where('id', '[0-9]+');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/profile/index', 'User\ProfileController@index');
    Route::get('/user/profile/map', 'User\ProfileController@geojson');
    Route::get('/user/profile/download', 'User\ProfileController@download');
    Route::get('/user/profile/photos/index', 'User\UserPhotoController@index');
    Route::get('/user/profile/photos/previous-custom-tags', 'User\UserPhotoController@previousCustomTags');
    Route::get('/user/profile/photos/filter', 'User\UserPhotoController@filter');
    Route::post('/user/profile/photos/tags/bulkTag', 'User\UserPhotoController@bulkTag');
    Route::post('/user/profile/photos/delete', 'User\UserPhotoController@destroy');
    Route::post('/profile/upload-profile-photo', 'UsersController@uploadProfilePhoto');
    // Removed: POST /profile/photos/remaining/{id} — toggled deprecated `remaining` column
    Route::post('/profile/photos/delete', 'PhotosController@deleteImage');
});

/*
|--------------------------------------------------------------------------
| Settings (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/settings/details', 'UsersController@details');
    Route::patch('/settings/details/password', 'UsersController@changePassword');
    // Route removed: /settings/delete — had no relationship cleanup. Use /settings/delete-account.
    // Route removed: /settings/security — wrote to non-existent columns.
    Route::post('/settings/privacy/update', 'UsersController@togglePrivacy');
    Route::post('/settings/phone/submit', 'UsersController@phone');
    Route::post('/settings/phone/remove', 'UsersController@removePhone');
    Route::post('/settings/toggle', 'UsersController@togglePresence');
    Route::post('/settings/email/toggle', 'EmailSubController@toggleEmailSub');
    Route::get('/settings/flags/countries', 'SettingsController@getCountries');
    Route::post('/settings/save-flag', 'SettingsController@saveFlag');
    Route::patch('/settings', 'SettingsController@update');
});

// Settings — auth:sanctum supports both session (SPA) and token (mobile) auth
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/settings/privacy/maps/name', 'ApiSettingsController@mapsName');
    Route::post('/settings/privacy/maps/username', 'ApiSettingsController@mapsUsername');
    Route::post('/settings/privacy/leaderboard/name', 'ApiSettingsController@leaderboardName');
    Route::post('/settings/privacy/leaderboard/username', 'ApiSettingsController@leaderboardUsername');
    Route::post('/settings/privacy/createdby/name', 'ApiSettingsController@createdByName');
    Route::post('/settings/privacy/createdby/username', 'ApiSettingsController@createdByUsername');
    Route::post('/settings/update', 'ApiSettingsController@update');
    Route::post('/settings/privacy/toggle-previous-tags', 'ApiSettingsController@togglePreviousTags');
    Route::post('/settings/delete-account', 'API\DeleteAccountController');
});

/*
|--------------------------------------------------------------------------
| Teams
|--------------------------------------------------------------------------
*/

Route::prefix('/teams')->group(function () {
    // Public — no auth required
    Route::get('/types', 'API\TeamsController@types');

    // Authenticated — SPA (session) + mobile (Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/members', 'API\TeamsController@members');
        Route::get('/leaderboard', 'Teams\TeamsLeaderboardController@index');
        Route::get('/list', 'API\TeamsController@list');
        Route::get('/data', 'Teams\TeamsDataController@index');
        Route::get('/clusters/{team}', 'Teams\TeamsClusterController@clusters');
        Route::get('/points/{team}', 'Teams\TeamsClusterController@points');
        Route::get('/joined', 'Teams\TeamsController@joined');
        Route::patch('/update/{team}', 'API\TeamsController@update');
        Route::post('/active', 'API\TeamsController@setActiveTeam');
        Route::post('/create', 'API\TeamsController@create');
        Route::post('/download', 'API\TeamsController@download');
        Route::post('/inactivate', 'API\TeamsController@inactivateTeams');
        Route::post('/join', 'API\TeamsController@join');
        Route::post('/leave', 'API\TeamsController@leave');
        Route::post('/leaderboard/visibility', 'Teams\TeamsLeaderboardController@toggle');
        Route::post('/settings', 'Teams\TeamsSettingsController@index');

        // Team Photos — CRUD + approval (school teams)
        Route::prefix('/photos')->group(function () {
            Route::get('/', 'Teams\TeamPhotosController@index');
            Route::get('/map', 'Teams\TeamPhotosController@mapPoints');
            Route::get('/member-stats', 'Teams\TeamPhotosController@memberStats');
            Route::get('/{photo}', 'Teams\TeamPhotosController@show');
            Route::patch('/{photo}/tags', 'Teams\TeamPhotosController@updateTags');
            Route::post('/approve', 'Teams\TeamPhotosController@approve');
            Route::post('/revoke', 'Teams\TeamPhotosController@revoke');
            Route::delete('/{photo}', 'Teams\TeamPhotosController@destroy');
        });

        // Participant Management (facilitator — team leader)
        Route::prefix('/{team}/participants')->group(function () {
            Route::get('/', 'Teams\ParticipantController@index');
            Route::post('/', 'Teams\ParticipantController@store');
            Route::post('/{participant}/deactivate', 'Teams\ParticipantController@deactivate');
            Route::post('/{participant}/activate', 'Teams\ParticipantController@activate');
            Route::post('/{participant}/reset-token', 'Teams\ParticipantController@resetToken');
            Route::delete('/{participant}', 'Teams\ParticipantController@destroy');
        });
    });
});

// Participant Session — public (no auth, token-based)
Route::post('/participant/session', 'Teams\ParticipantSessionController@enter');

// Participant Workspace — token auth via middleware
Route::prefix('/participant')->middleware('participant')->group(function () {
    Route::post('/upload', \App\Http\Controllers\Uploads\UploadPhotoController::class);
    Route::post('/tags', [\App\Http\Controllers\API\Tags\PhotoTagsController::class, 'store']);
    Route::get('/photos', 'Teams\ParticipantPhotoController@index');
    Route::delete('/photos/{photo}', 'Teams\ParticipantPhotoController@destroy');
});

/*
|--------------------------------------------------------------------------
| Leaderboard
|--------------------------------------------------------------------------
*/

Route::get('/leaderboard', LeaderboardController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/achievements', [AchievementsController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/redis-data', [RedisDataController::class, 'index']);
    Route::get('/redis-data/performance', [RedisDataController::class, 'performance']);
    Route::get('/redis-data/key-analysis', [RedisDataController::class, 'keyAnalysis']);
    Route::get('/redis-data/{userId}', [RedisDataController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Global map
|--------------------------------------------------------------------------
*/

// Moved from web.php
Route::get('/global/points', 'Maps\GlobalMapController@index');
Route::get('/global/art-data', 'Maps\GlobalMapController@artData');
Route::get('/global/search/custom-tags', 'Maps\Search\FindCustomTagsController');

/*
|--------------------------------------------------------------------------
| Community & Map data (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::get('/community/stats', 'CommunityController@stats');
Route::get('/tags-search', 'DisplayTagsOnMapController@show');
Route::get('/city', 'MapController@getCity');
Route::get('/countries/names', 'Location\GetListOfCountriesController');
// Route::get('/get-world-cup-data', 'WorldCup\GetDataForWorldCupController'); // duplicate of /locations/world-cup

/*
|--------------------------------------------------------------------------
| Cleanups (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::post('/cleanups/create', 'Cleanups\CreateCleanupController');
Route::get('/cleanups/get-cleanups', 'Cleanups\GetCleanupsGeoJsonController');
Route::post('/cleanups/{inviteLink}/join', 'Cleanups\JoinCleanupController');
Route::post('/cleanups/{inviteLink}/leave', 'Cleanups\LeaveCleanupController');

/*
|--------------------------------------------------------------------------
| History (moved from web.php)
|--------------------------------------------------------------------------
*/

// Removed: GET /history/paginated — deprecated, use GET /api/v3/user/photos

/*
|--------------------------------------------------------------------------
| Downloads (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::post('/download', 'DownloadControllerNew@index');
// Route::get('/world/{country}/{state}/{city?}/download/get', 'DownloadsController@getDataByCity');

/*
|--------------------------------------------------------------------------
| Payments & Subscriptions (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::get('plans', function () { return \App\Plan::all(); });
// Route::post('/join', 'SubscriptionsController@store');
// Route::post('/change', 'SubscriptionsController@change');
// Route::post('/settings/payments/cancel', 'SubscriptionsController@destroy');
// Route::post('/settings/payments/reactivate', 'SubscriptionsController@resume');
// Route::post('/subscribe', 'SubscribersController');
// Route::get('/stripe/subscriptions', 'StripeController@subscriptions');
// Route::post('/stripe/delete', 'StripeController@delete');
// Route::post('/stripe/resubscribe', 'StripeController@resubscribe');
// Route::post('/stripe/webhook', 'WebhookController@handleWebhook')->name('webhook');

/*
|--------------------------------------------------------------------------
| Donate (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::get('/donate/amounts', 'DonateController@index');
// Route::post('/donate', 'DonateController@submit');

/*
|--------------------------------------------------------------------------
| Contact (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::post('/contact-us', 'ContactUsController');

/*
|--------------------------------------------------------------------------
| Littercoin (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::post('/littercoin/merchants', 'Merchants\BecomeAMerchantController');

// Route::get('/get-users-littercoin', 'Littercoin\LittercoinController@getUsersLittercoin');
// Route::post('/wallet-info', 'Littercoin\LittercoinController@getWalletInfo');
// Route::post('/littercoin-mint-tx', 'Littercoin\LittercoinController@mintTx');
// Route::post('/littercoin-submit-mint-tx', 'Littercoin\LittercoinController@submitMintTx');
// Route::post('/littercoin-burn-tx', 'Littercoin\LittercoinController@burnTx');
// Route::post('/littercoin-submit-burn-tx', 'Littercoin\LittercoinController@submitBurnTx');
// Route::post('/merchant-mint-tx', 'Littercoin\LittercoinController@merchTx');
// Route::post('/merchant-submit-mint-tx', 'Littercoin\LittercoinController@submitMerchTx');

/*
|--------------------------------------------------------------------------
| Merchants (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::post('/merchants/create', 'Littercoin\Merchants\CreateMerchantController');
// Route::get('/merchants/get-geojson', 'Littercoin\Merchants\GetMerchantsGeojsonController');
// Route::get('/merchants/get-next-merchant-to-approve', 'Littercoin\Merchants\GetNextMerchantToApproveController');
// Route::post('/merchants/upload-photo', 'Merchants\UploadMerchantPhotoController');

/*
|--------------------------------------------------------------------------
| Admin (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => '/admin', 'middleware' => 'admin'], function () {
    Route::get('/photos', 'Admin\AdminQueueController');
    Route::get('/find-photo-by-id', 'Admin\FindPhotoByIdController');
    Route::get('/get-next-image-to-verify', 'Admin\GetNextImageToVerifyController');
    Route::get('/get-countries-with-photos', 'AdminController@getCountriesWithPhotos');
    Route::get('/go-back-one', 'Admin\GoBackOnePhotoController');
    Route::post('/verify', 'AdminController@verify');
    Route::post('/verify-tags-as-correct', 'Admin\VerifyImageWithTagsController');
    Route::post('/reset-tags', 'Admin\AdminResetTagsController');
    Route::post('/contentsupdatedelete', 'AdminController@updateDelete');
    Route::post('/update-tags', 'Admin\UpdateTagsController');
    Route::post('/destroy', 'AdminController@destroy');
    Route::post('/merchants/approve', 'Littercoin\Merchants\ApproveMerchantController');
    Route::post('/merchants/delete', 'Littercoin\Merchants\DeleteMerchantController');

    // Dashboard stats
    Route::get('/stats', 'Admin\AdminStatsController');

    // User management
    Route::get('/users', 'Admin\AdminUsersController@index');
    Route::post('/users/{user}/trust', 'Admin\AdminUsersController@trust');
    Route::post('/users/{user}/approve-all', 'Admin\AdminUsersController@approveAll');
    Route::post('/users/{user}/school-manager', 'Admin\AdminUsersController@toggleSchoolManager');
    Route::patch('/users/{user}/username', 'Admin\AdminUsersController@updateUsername');

    // Impersonation (start requires admin middleware — stop is below, outside this group)
    Route::post('/users/{user}/impersonate', 'Admin\AdminImpersonateController@start');
});

// Impersonate stop — outside admin group since session is the impersonated user
Route::post('/impersonate/stop', 'Admin\AdminImpersonateController@stop')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Bbox (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => '/bbox', 'middleware' => ['can_bbox']], function () {
    Route::get('/index', 'Bbox\BoundingBoxController@index');
    Route::post('/create', 'Bbox\BoundingBoxController@create');
    Route::post('/skip', 'Bbox\BoundingBoxController@skip');
    Route::post('/tags/update', 'Bbox\BoundingBoxController@updateTags');
    Route::post('/tags/wrong', 'Bbox\BoundingBoxController@wrongTags');
    Route::get('/verify/index', 'Bbox\VerifyBoxController@index');
    Route::post('/verify/update', 'Bbox\VerifyBoxController@update');
});

/*
|--------------------------------------------------------------------------
| Legacy location routes (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::get('/location', 'Location\LocationsController@index');
// Route::get('/states', 'Location\LocationsController@getStates');
// Route::get('/cities', 'Location\LocationsController@getCities');
