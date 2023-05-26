<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use App\Models\Cleanups\CleanupUser;
use Illuminate\Http\Request;

class LeaveCleanupController extends Controller
{
    /**
     * Leave a cleanup
     *
     * User who created the cleanup should not be able to leave it
     */
    public function __invoke ($link)
    {
        $cleanup = Cleanup::where('invite_link', $link)->first();

        if (!$cleanup)
        {
            return [
                'success' => false,
                'msg' => 'not found'
            ];
        }

        // Check if the user is already a part of the cleanup
        $user = auth()->user();

        if ($cleanup->user_id === $user->id) {
            return [
                'success' => false,
                'msg' => 'cannot leave'
            ];
        }

        $exists = CleanupUser::where([
            'cleanup_id' => $cleanup->id,
            'user_id' => $user->id
        ])->first();

        if (!$exists) {
            return [
                'success' => false,
                'msg' => 'already left'
            ];
        }

        try {
            $cleanup->users()->detach($user);
        }
        catch (\Exception $e) {
            \Log::info(['LeaveCleanupController', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'problem leaving cleanup'
            ];
        }

        return [
            'success' => true
        ];
    }
}
