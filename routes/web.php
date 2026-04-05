<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Reports\GenerateImpactReportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Non-SPA routes (must be defined before the catch-all)
|--------------------------------------------------------------------------
*/

// Impact reports (renders its own HTML, not the SPA)
Route::get('impact/{period?}/{year?}/{monthOrWeek?}', GenerateImpactReportController::class);

// Email confirmation
Route::get('register/confirm/{token}', 'Auth\RegisterController@confirmEmail');
Route::get('confirm/email/{token}', 'Auth\RegisterController@confirmEmail')
    ->name('confirm-email-token');

// Email unsubscribe (unauthenticated, token-based)
Route::get('/emails/unsubscribe/{token}', 'EmailSubController@unsubEmail');
Route::get('/unsubscribe/{token}', 'UsersController@unsubscribeEmail');

// Logout
Route::get('logout', 'UsersController@logout');

// Auth check (JSON, but needs web middleware for session)
Route::get('/check-auth', fn () => response()->json(['success' => Auth::check()]));

// Password reset — named route for the email notification link
Route::get('password/reset/{token}', HomeController::class)->name('password.reset');

/*
|--------------------------------------------------------------------------
| SPA catch-all — must be last
|--------------------------------------------------------------------------
|
| Every GET request that doesn't match a route above lands here.
| Vue Router handles client-side routing from this point.
|
*/

Route::get('/{any?}', HomeController::class)->where('any', '.*');
