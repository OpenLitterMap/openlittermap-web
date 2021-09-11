<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Events\UserSignedUp;
use Exception;
use App\Mail\NewUserRegMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use League\OAuth2\Server\Exception\OAuthServerException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiRegisterController extends Controller
{
    /**
     * Create a new account via API
     */
    public function register (Request $request)
    {
    	$this->validate($request, [
            'email'    => 'required|email|max:75|unique:users',
            'password' => 'required|min:6|max:255|case_diff|numbers|letters',
            'username' => 'required|min:3|max:20|unique:users|different:password',
        ]);

        $email = $request->email;

        event(new Registered($user = $this->create($request->all())));

        Mail::to($email)->send(new NewUserRegMail($user));

        event(new UserSignedUp(now()));

        return ['success' => 'Success! Your account has been created.'];
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
