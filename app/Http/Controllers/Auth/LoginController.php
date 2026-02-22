<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Login via email or username.
     *
     * Uses session auth (Sanctum stateful) for SPA.
     * For external API consumers (e.g. LitterWeek), use Passport OAuth tokens instead.
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('identifier');
        $password = $request->input('password');

        // Determine if identifier is email or username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $identifier, 'password' => $password], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'identifier' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Logout and invalidate session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
