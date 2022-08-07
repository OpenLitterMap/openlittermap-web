<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use App\Models\Cleanups\CleanupUser;
use Illuminate\Http\Request;

class JoinCleanupController extends Controller
{
    /**
     * Join a cleanup
     */
    public function __invoke ($link)
    {
        $cleanup = Cleanup::with(['users' => function ($q) {
            $q->select('user_id');
        }])
        ->where('invite_link', $link)
        ->first();

        if (!$cleanup)
        {
            return [
                'success' => false,
                'msg' => 'cleanup not found'
            ];
        }

        // If the user is not logged in
        // - zoom to location
        // - ask them to create an account
        if (!auth()->check())
        {
            return [
                'success' => false,
                'msg' => 'unauthenticated',
                'cleanup' => $cleanup
            ];
        }

        // Check if the user is already a part of the cleanup
        $user = auth()->user();

        $exists = CleanupUser::where([
            'cleanup_id' => $cleanup->id,
            'user_id' => $user->id
        ])->first();

        if ($exists)
        {
            return [
                'success' => false,
                'msg' => 'already joined',
                'cleanup' => $cleanup
            ];
        }

        try {
            $cleanup->users()->attach($user);
        }
        catch (\Exception $e) {
            \Log::info(['JoinCleanupController', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem joining cleanup'
            ];
        }

        return [
            'success' => true,
            'cleanup' => $cleanup
        ];
    }
}
