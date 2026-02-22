<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Validate a reset token without consuming it.
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
        ]);

        $user = Password::broker()->getUser($request->only('email'));

        if (! $user || ! Password::broker()->tokenExists($user, $request->token)) {
            return response()->json(['valid' => false], 422);
        }

        return response()->json(['valid' => true]);
    }

    /**
     * Reset the given user's password and log them in.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(5)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $user = Auth::user();

            return response()->json([
                'message' => trans($status),
                'user' => $user,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
