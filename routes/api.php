<?php

use App\Http\Controllers\Achievements\AchievementsController;
use App\Http\Controllers\Admin\AdminImpersonateController;
use App\Http\Controllers\Admin\AdminQueueController;
use App\Http\Controllers\Admin\AdminResetTagsController;
use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\FindPhotoByIdController;
use App\Http\Controllers\Admin\GetNextImageToVerifyController;
use App\Http\Controllers\Admin\GoBackOnePhotoController;
use App\Http\Controllers\Admin\UpdateTagsController;
use App\Http\Controllers\Admin\VerifyImageWithTagsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\API\DeleteAccountController;
use App\Http\Controllers\API\GlobalStatsController;
use App\Http\Controllers\API\MobileAppVersionController;
use App\Http\Controllers\API\Tags\GetTagsController;
use App\Http\Controllers\API\Tags\PhotoTagsController;
use App\Http\Controllers\API\TeamsController as APITeamsController;
use App\Http\Controllers\ApiSettingsController;
use App\Http\Controllers\Auth\AuthTokenController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Bbox\BoundingBoxController;
use App\Http\Controllers\Bbox\VerifyBoxController;
use App\Http\Controllers\Cleanups\CreateCleanupController;
use App\Http\Controllers\Cleanups\GetCleanupsGeoJsonController;
use App\Http\Controllers\Cleanups\JoinCleanupController;
use App\Http\Controllers\Cleanups\LeaveCleanupController;
use App\Http\Controllers\Clusters\ClusterController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DisplayTagsOnMapController;
use App\Http\Controllers\DownloadControllerNew;
use App\Http\Controllers\EmailSubController;
use App\Http\Controllers\Leaderboard\LeaderboardController;
use App\Http\Controllers\Littercoin\Merchants\ApproveMerchantController;
use App\Http\Controllers\Littercoin\Merchants\DeleteMerchantController;
use App\Http\Controllers\Location\GetListOfCountriesController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Location\TagController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\Maps\GlobalMapController;
use App\Http\Controllers\Maps\Search\FindCustomTagsController;
use App\Http\Controllers\Merchants\BecomeAMerchantController;
use App\Http\Controllers\PhotosController;
use App\Http\Controllers\Points\PointsController;
use App\Http\Controllers\Points\PointsStatsController;
use App\Http\Controllers\RedisDataController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Teams\ParticipantController;
use App\Http\Controllers\Teams\ParticipantPhotoController;
use App\Http\Controllers\Teams\ParticipantSessionController;
use App\Http\Controllers\Teams\TeamsClusterController;
use App\Http\Controllers\Teams\TeamsController;
use App\Http\Controllers\Teams\TeamsDataController;
use App\Http\Controllers\Teams\TeamsLeaderboardController;
use App\Http\Controllers\Teams\TeamsSettingsController;
use App\Http\Controllers\Teams\TeamPhotosController;
use App\Http\Controllers\Uploads\UploadPhotoController;
use App\Http\Controllers\User\Photos\UsersUploadsController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\UserPhotoController;
use App\Http\Controllers\UsersController;
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
Route::get('/global/stats-data', [GlobalStatsController::class, 'index']);
Route::get('/mobile-app-version', MobileAppVersionController::class);
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

Route::post('/auth/login', [LoginController::class, 'login'])
    ->middleware(app()->isLocal() ? ['web'] : ['web', 'throttle:5,1']);

Route::post('/auth/token', [AuthTokenController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/auth/logout', [LoginController::class, 'logout'])
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

Route::get('/user/profile/{id}', [ProfileController::class, 'show'])->where('id', '[0-9]+');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/profile/index', [ProfileController::class, 'index']);
    Route::get('/user/profile/map', [ProfileController::class, 'geojson']);
    Route::get('/user/profile/download', [ProfileController::class, 'download']);
    Route::get('/user/profile/photos/index', [UserPhotoController::class, 'index']);
    Route::get('/user/profile/photos/previous-custom-tags', [UserPhotoController::class, 'previousCustomTags']);
    Route::get('/user/profile/photos/filter', [UserPhotoController::class, 'filter']);
    Route::post('/user/profile/photos/tags/bulkTag', [UserPhotoController::class, 'bulkTag']);
    Route::post('/user/profile/photos/delete', [UserPhotoController::class, 'destroy']);
    Route::post('/profile/upload-profile-photo', [UsersController::class, 'uploadProfilePhoto']);
    // Removed: POST /profile/photos/remaining/{id} — toggled deprecated `remaining` column
    Route::post('/profile/photos/delete', [PhotosController::class, 'deleteImage']);
});

/*
|--------------------------------------------------------------------------
| Settings (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/settings/details', [UsersController::class, 'details']);
    Route::patch('/settings/details/password', [UsersController::class, 'changePassword']);
    // Route removed: /settings/delete — had no relationship cleanup. Use /settings/delete-account.
    // Route removed: /settings/security — wrote to non-existent columns.
    Route::post('/settings/privacy/update', [UsersController::class, 'togglePrivacy']);
    Route::post('/settings/phone/submit', [UsersController::class, 'phone']);
    Route::post('/settings/phone/remove', [UsersController::class, 'removePhone']);
    Route::post('/settings/toggle', [UsersController::class, 'togglePresence']);
    Route::post('/settings/email/toggle', [EmailSubController::class, 'toggleEmailSub']);
    Route::get('/settings/flags/countries', [SettingsController::class, 'getCountries']);
    Route::post('/settings/save-flag', [SettingsController::class, 'saveFlag']);
    Route::patch('/settings', [SettingsController::class, 'update']);
});

// Settings — auth:sanctum supports both session (SPA) and token (mobile) auth
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/settings/privacy/maps/name', [ApiSettingsController::class, 'mapsName']);
    Route::post('/settings/privacy/maps/username', [ApiSettingsController::class, 'mapsUsername']);
    Route::post('/settings/privacy/leaderboard/name', [ApiSettingsController::class, 'leaderboardName']);
    Route::post('/settings/privacy/leaderboard/username', [ApiSettingsController::class, 'leaderboardUsername']);
    Route::post('/settings/privacy/createdby/name', [ApiSettingsController::class, 'createdByName']);
    Route::post('/settings/privacy/createdby/username', [ApiSettingsController::class, 'createdByUsername']);
    Route::post('/settings/update', [ApiSettingsController::class, 'update']);
    Route::post('/settings/privacy/toggle-previous-tags', [ApiSettingsController::class, 'togglePreviousTags']);
    Route::post('/settings/delete-account', DeleteAccountController::class);
});

/*
|--------------------------------------------------------------------------
| Teams
|--------------------------------------------------------------------------
*/

Route::prefix('/teams')->group(function () {
    // Public — no auth required
    Route::get('/types', [APITeamsController::class, 'types']);

    // Authenticated — SPA (session) + mobile (Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/members', [APITeamsController::class, 'members']);
        Route::get('/leaderboard', [TeamsLeaderboardController::class, 'index']);
        Route::get('/list', [APITeamsController::class, 'list']);
        Route::get('/data', [TeamsDataController::class, 'index']);
        Route::get('/clusters/{team}', [TeamsClusterController::class, 'clusters']);
        Route::get('/points/{team}', [TeamsClusterController::class, 'points']);
        Route::get('/joined', [TeamsController::class, 'joined']);
        Route::patch('/update/{team}', [APITeamsController::class, 'update']);
        Route::post('/active', [APITeamsController::class, 'setActiveTeam']);
        Route::post('/create', [APITeamsController::class, 'create']);
        Route::post('/download', [APITeamsController::class, 'download']);
        Route::post('/inactivate', [APITeamsController::class, 'inactivateTeams']);
        Route::post('/join', [APITeamsController::class, 'join']);
        Route::post('/leave', [APITeamsController::class, 'leave']);
        Route::post('/leaderboard/visibility', [TeamsLeaderboardController::class, 'toggle']);
        Route::post('/settings', [TeamsSettingsController::class, 'index']);

        // Team Photos — CRUD + approval (school teams)
        Route::prefix('/photos')->group(function () {
            Route::get('/', [TeamPhotosController::class, 'index']);
            Route::get('/map', [TeamPhotosController::class, 'mapPoints']);
            Route::get('/member-stats', [TeamPhotosController::class, 'memberStats']);
            Route::get('/{photo}', [TeamPhotosController::class, 'show']);
            Route::patch('/{photo}/tags', [TeamPhotosController::class, 'updateTags']);
            Route::post('/approve', [TeamPhotosController::class, 'approve']);
            Route::post('/revoke', [TeamPhotosController::class, 'revoke']);
            Route::delete('/{photo}', [TeamPhotosController::class, 'destroy']);
        });

        // Participant Management (facilitator — team leader)
        Route::prefix('/{team}/participants')->group(function () {
            Route::get('/', [ParticipantController::class, 'index']);
            Route::post('/', [ParticipantController::class, 'store']);
            Route::post('/{participant}/deactivate', [ParticipantController::class, 'deactivate']);
            Route::post('/{participant}/activate', [ParticipantController::class, 'activate']);
            Route::post('/{participant}/reset-token', [ParticipantController::class, 'resetToken']);
            Route::delete('/{participant}', [ParticipantController::class, 'destroy']);
        });
    });
});

// Participant Session — public (no auth, token-based)
Route::post('/participant/session', [ParticipantSessionController::class, 'enter']);

// Participant Workspace — token auth via middleware
Route::prefix('/participant')->middleware('participant')->group(function () {
    Route::post('/upload', UploadPhotoController::class);
    Route::post('/tags', [PhotoTagsController::class, 'store']);
    Route::get('/photos', [ParticipantPhotoController::class, 'index']);
    Route::delete('/photos/{photo}', [ParticipantPhotoController::class, 'destroy']);
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
Route::get('/global/points', [GlobalMapController::class, 'index']);
Route::get('/global/art-data', [GlobalMapController::class, 'artData']);
Route::get('/global/search/custom-tags', FindCustomTagsController::class);

/*
|--------------------------------------------------------------------------
| Community & Map data (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::get('/community/stats', [CommunityController::class, 'stats']);
Route::get('/tags-search', [DisplayTagsOnMapController::class, 'show']);
Route::get('/city', [MapController::class, 'getCity']);
Route::get('/countries/names', GetListOfCountriesController::class);
// Route::get('/get-world-cup-data', 'WorldCup\GetDataForWorldCupController'); // duplicate of /locations/world-cup

/*
|--------------------------------------------------------------------------
| Cleanups (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::post('/cleanups/create', CreateCleanupController::class);
Route::get('/cleanups/get-cleanups', GetCleanupsGeoJsonController::class);
Route::post('/cleanups/{inviteLink}/join', JoinCleanupController::class);
Route::post('/cleanups/{inviteLink}/leave', LeaveCleanupController::class);

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

Route::post('/download', [DownloadControllerNew::class, 'index']);
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

Route::post('/littercoin/merchants', BecomeAMerchantController::class);

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
    Route::get('/photos', AdminQueueController::class);
    Route::get('/find-photo-by-id', FindPhotoByIdController::class);
    Route::get('/get-next-image-to-verify', GetNextImageToVerifyController::class);
    Route::get('/get-countries-with-photos', [AdminController::class, 'getCountriesWithPhotos']);
    Route::get('/go-back-one', GoBackOnePhotoController::class);
    Route::post('/verify', [AdminController::class, 'verify']);
    Route::post('/verify-tags-as-correct', VerifyImageWithTagsController::class);
    Route::post('/reset-tags', AdminResetTagsController::class);
    Route::post('/contentsupdatedelete', [AdminController::class, 'updateDelete']);
    Route::post('/update-tags', UpdateTagsController::class);
    Route::post('/destroy', [AdminController::class, 'destroy']);
    Route::post('/merchants/approve', ApproveMerchantController::class);
    Route::post('/merchants/delete', DeleteMerchantController::class);

    // Dashboard stats
    Route::get('/stats', AdminStatsController::class);

    // User management
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::post('/users/{user}/trust', [AdminUsersController::class, 'trust']);
    Route::post('/users/{user}/approve-all', [AdminUsersController::class, 'approveAll']);
    Route::post('/users/{user}/school-manager', [AdminUsersController::class, 'toggleSchoolManager']);
    Route::patch('/users/{user}/username', [AdminUsersController::class, 'updateUsername']);

    // Impersonation (start requires admin middleware — stop is below, outside this group)
    Route::post('/users/{user}/impersonate', [AdminImpersonateController::class, 'start']);
});

// Impersonate stop — outside admin group since session is the impersonated user
Route::post('/impersonate/stop', [AdminImpersonateController::class, 'stop'])
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Bbox (moved from web.php)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => '/bbox', 'middleware' => ['can_bbox']], function () {
    Route::get('/index', [BoundingBoxController::class, 'index']);
    Route::post('/create', [BoundingBoxController::class, 'create']);
    Route::post('/skip', [BoundingBoxController::class, 'skip']);
    Route::post('/tags/update', [BoundingBoxController::class, 'updateTags']);
    Route::post('/tags/wrong', [BoundingBoxController::class, 'wrongTags']);
    Route::get('/verify/index', [VerifyBoxController::class, 'index']);
    Route::post('/verify/update', [VerifyBoxController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Legacy location routes (moved from web.php)
|--------------------------------------------------------------------------
*/

// Route::get('/location', 'Location\LocationsController@index');
// Route::get('/states', 'Location\LocationsController@getStates');
// Route::get('/cities', 'Location\LocationsController@getCities');
