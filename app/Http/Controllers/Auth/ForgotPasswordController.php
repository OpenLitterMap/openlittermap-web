<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        $login = $request->input('login');

        // Resolve to email — could be username or email
        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Exception $e) {
                \Log::info($e->getMessage());
                report($e); // logs it without exposing to user
            }
        }

        // Always return the same message to prevent user enumeration
        return response()->json([
            'message' => 'If an account with these details exists, we will send a password reset link.',
        ]);
    }
}
