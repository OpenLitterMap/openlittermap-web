<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Events\UserSignedUp;
use App\Mail\NewUserRegMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules\Password;

class ApiRegisterController extends Controller
{
    /**
     * Create a new account via API
     */
    public function register (Request $request): JsonResponse
    {
    	$this->validate($request, [
            'email'    => 'required|email|max:75|unique:users',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
            'username' => 'required|min:3|max:20|unique:users|different:password',
        ]);

        event(new Registered($user = $this->create($request->all())));

        Mail::to($request->email)->send(new NewUserRegMail($user));

        event(new UserSignedUp(now()));

        return response()->json([
            'success' => 'Success! Your account has been created.'
        ]);
    }

    protected function create (array $data)
    {
        return User::create([
            'name' => $data['name'] ?? 'default',
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'images_remaining' => 999
        ]);
    }
}
