<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminImpersonateController extends Controller
{
    /**
     * Start impersonating a user.
     *
     * Superadmin only. Cannot impersonate other admins/superadmins.
     * Stores original admin ID in session for restoration.
     */
    public function start(User $user): JsonResponse
    {
        $admin = Auth::user();

        if (! $admin->hasRole('superadmin')) {
            return response()->json(['message' => 'Superadmin role required.'], 403);
        }

        if ($user->id === $admin->id) {
            return response()->json(['message' => 'Cannot impersonate yourself.'], 422);
        }

        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['message' => 'Cannot impersonate admin users.'], 403);
        }

        // Store the original admin ID so we can switch back
        session()->put('impersonating_from', $admin->id);

        Auth::guard('web')->login($user);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'username' => $user->username,
        ]);
    }

    /**
     * Stop impersonating and return to the original admin session.
     *
     * This route is NOT behind admin middleware since the current
     * session is the impersonated (non-admin) user.
     */
    public function stop(): JsonResponse
    {
        $adminId = session()->get('impersonating_from');

        if (! $adminId) {
            return response()->json(['message' => 'Not currently impersonating.'], 422);
        }

        $admin = User::find($adminId);

        if (! $admin) {
            session()->forget('impersonating_from');

            return response()->json(['message' => 'Original admin account not found.'], 404);
        }

        session()->forget('impersonating_from');
        Auth::guard('web')->login($admin);

        return response()->json([
            'success' => true,
            'user_id' => $admin->id,
            'username' => $admin->username,
        ]);
    }
}
