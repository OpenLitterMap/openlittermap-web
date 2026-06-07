<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ResolvesUserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use ResolvesUserProfile;

    /**
     * Maximum failed login attempts per identifier + IP before lockout.
     */
    protected const MAX_ATTEMPTS = 7;

    /**
     * Lockout window in seconds once the attempt limit is reached.
     */
    protected const DECAY_SECONDS = 60;
    /**
     * Login via email or username.
     *
     * Uses session auth (Sanctum stateful) for SPA.
     * For mobile/external API consumers, use POST /api/auth/token (Sanctum token).
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

        $this->ensureIsNotRateLimited($request);

        $identifier = $request->input('identifier');
        $password = $request->input('password');

        // Determine if identifier is email or username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $identifier, 'password' => $password], $request->boolean('remember'))) {
            if (! app()->isLocal()) {
                RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);
            }

            throw ValidationException::withMessages([
                'identifier' => [__('auth.failed')],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        $user = Auth::user();

        $profileData = $this->buildFullProfileData($user);

        return response()->json([
            'success' => true,
            ...$profileData,
        ]);
    }

    /**
     * Reject the request if too many failed attempts have been made for this
     * identifier + IP combination. Keying on the identifier means one user's
     * mistakes (or a single brute-forced account) cannot lock out everyone
     * else sharing the same IP — important for schools behind one NAT.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (app()->isLocal()) {
            return;
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        $exception = ValidationException::withMessages([
            'identifier' => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);

        $exception->status = 429;

        throw $exception;
    }

    /**
     * Build the rate-limiter key from the login identifier and request IP.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('identifier')).'|'.$request->ip()
        );
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
