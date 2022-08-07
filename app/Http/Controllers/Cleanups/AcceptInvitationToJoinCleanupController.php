<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use App\Models\Cleanups\CleanupUser;
use Illuminate\Http\Request;

class AcceptInvitationToJoinCleanupController extends Controller
{
    /**
     * Get request---
     *
     * User has clicked on an invitation to join a cleanup
     */
    public function __invoke ($link)
    {
        if (!auth()->user())
        {
            return [
                'success' => false,
                'msg' => 'need to login'
            ];
        }

        $cleanup = Cleanup::where('invite_link', $link)->first();

        if (!$cleanup)
        {
            return [
                'success' => false,
                'msg' => 'cleanup not found'
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
                'msg' => 'already joined'
            ];
        }
    }
}
