<?php

namespace App\Http\Controllers\Auth;

use App\Models\Users\User;
use App\Mail\NewUserRegMail;
use App\Events\UserSignedUp;
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
        $this->middleware('guest')->except([]);
    }

    protected function create(array $data): User
    {
        return User::create([
            'name' => $data['name'] ?? '',
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Handle a registration request for both web and API.
     *
     * @throws ValidationException
     */
    public function register(Request $request): array
    {
        $this->validate($request, [
            'name' => 'sometimes|nullable|string|max:25',
            'username' => 'required|min:3|max:20|unique:users|different:password',
            'email' => 'required|email|max:75|unique:users',
            'password' => ['required', Password::min(5)],
        ]);

        event(new Registered($user = $this->create($request->all())));

        Mail::to($request->email)->send(new NewUserRegMail($user));

        event(new UserSignedUp(now()));

        $user->images_remaining = 1000;
        $user->verify_remaining = 5000;
        $user->save();

        Auth::login($user);

        return [
            'user_id' => $user->id,
            'email' => $user->email,
        ];
    }

    /**
     * The user clicked the confirm email link.
     */
    public function confirmEmail($token)
    {
        /** @var User $user */
        $user = User::whereToken($token)->first();

        $verified = $user && $user->confirmEmail();

        $auth = false;
        $user = null;
        $unsub = false;

        return view('root', compact('auth', 'user', 'verified', 'unsub'));
    }
}
