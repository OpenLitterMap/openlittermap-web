<?php

use App\Http\Controllers\Achievements\AchievementsController;
// use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\ApiPhotosController;
use App\Http\Controllers\Clusters\ClusterController;
use App\Http\Controllers\Leaderboard\GetUsersForGlobalLeaderboardController;
use App\Http\Controllers\Leaderboard\GetUsersForLocationLeaderboardController;
use App\Http\Controllers\Leaderboard\LeaderboardController;
use App\Http\Controllers\RedisDataController;
use App\Models\Littercoin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\Tags\GetTagsController;
use App\Http\Controllers\API\Tags\PhotoTagsController;
use App\Http\Controllers\API\GetUntaggedUploadController;
use App\Http\Controllers\Photos\GetUsersUntaggedPhotosController;

Route::get('/tags', [GetTagsController::class, 'index']);
Route::get('/tags/all', [GetTagsController::class, 'getAllTags']);

// Route::post('/tags', [UploadTagsController::class, 'upload']);

Route::group(['prefix' => 'v2', 'middleware' => 'auth:api'], function(){

    // Route::get('/user/setup-intent', 'API\UserController@getSetupIntent');

    // old version
    Route::get('/photos/web/index', 'API\GetUntaggedUploadController');

    // new version
    Route::get('/photos/get-untagged-uploads', GetUntaggedUploadController::class);

    Route::get('/photos/web/load-more', 'API\WebPhotosController@loadMore');

    Route::post('/add-tags-to-uploaded-image', 'API\AddTagsToUploadedImageController');

    // Route::get('/uploads/history', 'API\GetMyPaginatedUploadsController');
});

Route::group(['prefix' => 'v3', 'middleware' => ['web', 'auth:api,web']], function () {

     Route::get('/user/photos/untagged', [GetUsersUntaggedPhotosController::class, 'index']);

     Route::post('/tags', [PhotoTagsController::class, 'store']);
});

Route::prefix('clusters')->group(function () {
    Route::get('/', [ClusterController::class, 'index']);
    Route::get('/zoom-levels', [ClusterController::class, 'zoomLevels']);
});

Route::get('/global/stats-data', 'API\GlobalStatsController@index');
Route::get('/mobile-app-version', 'API\MobileAppVersionController');

Route::post('add-tags', 'API\AddTagsToUploadedImageController')
    ->middleware('auth:api');

// Check if current token is valid
Route::post('/validate-token', function(Request $request) {
    return ['message' => 'valid'];
})->middleware('auth:api');

// Create Account
Route::post('/register', 'ApiRegisterController@register');

// Fetch User
Route::get('/user', function (Request $request) {
    $user = Auth::guard('api')->user()->append('position', 'xp_redis');

    $littercoin = Littercoin::where('user_id', $user->id)->count();

    $user['littercoin_count'] = $littercoin;

    return $user;
});

// Reset Password
Route::post('/password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail');

// Upload Photos
Route::post('/photos/submit', [ApiPhotosController::class, 'store'])
    ->middleware('auth:api');

// Upload Photos with tags - old route
Route::post('/photos/submit-with-tags', 'ApiPhotosController@uploadWithOrWithoutTags')
    ->middleware('auth:api');

// Upload Photos with tags - old route
Route::post('/photos/upload-with-tags', 'ApiPhotosController@uploadWithOrWithoutTags')
    ->middleware('auth:api');

// Upload Photos with or without tags -  new route
Route::post('/photos/upload/with-or-without-tags', 'ApiPhotosController@uploadWithOrWithoutTags')
    ->middleware('auth:api');

// Delete Photos
Route::delete('/photos/delete', 'ApiPhotosController@deleteImage');

// Check for any photos uploaded on web
Route::get('/check-web-photos', [ApiPhotosController::class, 'check'])
    ->middleware('auth:api');

/**
 * Settings
 */
Route::post('/settings/privacy/maps/name', 'ApiSettingsController@mapsName')->middleware('auth:api');
Route::post('/settings/privacy/maps/username', 'ApiSettingsController@mapsUsername')->middleware('auth:api');
Route::post('/settings/privacy/leaderboard/name', 'ApiSettingsController@leaderboardName')->middleware('auth:api');
Route::post('/settings/privacy/leaderboard/username', 'ApiSettingsController@leaderboardUsername')->middleware('auth:api');
Route::post('/settings/privacy/createdby/name', 'ApiSettingsController@createdByName')->middleware('auth:api');
Route::post('/settings/privacy/createdby/username', 'ApiSettingsController@createdByUsername')->middleware('auth:api');
Route::post('/settings/update', 'ApiSettingsController@update')->middleware('auth:api');
Route::post('/settings/privacy/toggle-previous-tags', 'ApiSettingsController@togglePreviousTags')->middleware('auth:api');
Route::patch('/settings', 'SettingsController@update')->middleware('auth:api');
Route::post('/settings/delete-account', 'API\DeleteAccountController')->middleware('auth:api');

/**
 * Sanctum Authenticated Routes
 */
Route::middleware('auth:sanctum')
    ->get('/achievements', [AchievementsController::class, 'index']);

/**
 * Littercoin
 */
Route::post('/littercoin/merchants', 'Merchants\BecomeAMerchantController');

// Teams
Route::prefix('/teams')->group(function () {
    Route::get('/members', 'API\TeamsController@members');
    Route::get('/leaderboard', 'Teams\TeamsLeaderboardController@index')->middleware('auth:api');
    Route::get('/list', 'API\TeamsController@list');
    Route::get('/types', 'API\TeamsController@types');
    Route::patch('/update/{team}', 'API\TeamsController@update');
    Route::post('/active', 'API\TeamsController@setActiveTeam');
    Route::post('/create', 'API\TeamsController@create');
    Route::post('/download', 'API\TeamsController@download');
    Route::post('/inactivate', 'API\TeamsController@inactivateTeams');
    Route::post('/join', 'API\TeamsController@join');
    Route::post('/leave', 'API\TeamsController@leave');
    Route::post('/leaderboard/visibility', 'Teams\TeamsLeaderboardController@toggle')->middleware('auth:api');
});


Route::middleware('auth:sanctum')->group(function () {

    // Get data for the Global Leaderboard - deprecated
//    Route::get('/global/leaderboard', GetUsersForGlobalLeaderboardController::class);
//    Route::get('/global/leaderboard/location', GetUsersForLocationLeaderboardController::class);

    Route::get('/leaderboard', LeaderboardController::class);

    // Needs admin middleware
    Route::get('/redis-data', [RedisDataController::class, 'index']);
    Route::get('/redis-data/{userId}', [RedisDataController::class, 'show']);
    Route::get('/redis-data/performance}', [RedisDataController::class, 'performance']);
    Route::get('/redis-data/key-analysis', [RedisDataController::class, 'keyAnalysis']);

});
