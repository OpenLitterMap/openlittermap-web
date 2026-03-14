<?php

namespace App\Http\Controllers\Auth;

use App\Models\Users\User;
use App\Mail\WelcomeToOpenLitterMap;
use App\Events\UserSignedUp;
use App\Services\UsernameGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    protected string $redirectTo = '/upload';

    public function __construct()
    {
        $this->middleware('guest')->except(['confirmEmail']);
    }

    protected function create(array $data): User
    {
        $username = ! empty($data['username'])
            ? $data['username']
            : UsernameGeneratorService::generate();

        return User::create([
            'name' => null,
            'username' => $username,
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Handle a registration request for both web and API.
     *
     * Returns a Sanctum token for mobile clients and also logs in
     * the session for SPA clients.
     *
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $this->validate($request, [
            'username' => 'sometimes|nullable|string|min:3|max:255|unique:users|regex:/^[a-zA-Z0-9_-]+$/',
            'email' => 'required|email|max:75|unique:users',
            'password' => ['required', Password::min(8)],
        ]);

        event(new Registered($user = $this->create($request->all())));

        if ($user->emailsub !== 0) {
            Mail::to($request->email)->send(new WelcomeToOpenLitterMap($user));
        }

        event(new UserSignedUp(now()));

        Auth::login($user);

        $token = $user->createToken('mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * The user clicked the confirm email link.
     */
    public function confirmEmail($token)
    {
        /** @var User $user */
        $user = User::whereToken($token)->first();

        $verified = $user && $user->confirmEmail();

        return redirect('/?verified=' . ($verified ? '1' : '0'));
    }
}
