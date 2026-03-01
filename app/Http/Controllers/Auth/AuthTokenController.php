<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /**
     * Issue a Sanctum token for mobile app authentication.
     *
     * Accepts email or username as identifier (same as SPA login).
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        // Accept 'identifier', 'email', or 'username' from mobile apps
        $request->merge([
            'identifier' => $request->input('identifier')
                ?? $request->input('email')
                ?? $request->input('username'),
        ]);

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('identifier');
        $password = $request->input('password');

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $identifier, 'password' => $password])) {
            throw ValidationException::withMessages([
                'identifier' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        // Revoke existing mobile tokens to prevent token buildup
        $user->tokens()->where('name', 'mobile')->delete();

        $token = $user->createToken('mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }
}
