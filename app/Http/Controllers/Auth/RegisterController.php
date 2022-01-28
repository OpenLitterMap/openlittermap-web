<?php

namespace App\Http\Controllers\Auth;

use App\Models\User\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

use App\Mail\NewUserRegMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use App\Events\UserSignedUp;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/submit';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct ()
    {
        $this->middleware('guest');
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create (array $data)
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  Request  $request
     * @return array
     */
    public function register (Request $request): array
    {
        $this->validate($request, [
            'name' => 'required|min:3|max:25',
            'username' => 'required|min:3|max:20|unique:users|different:password',
            'email' => 'required|email|max:75|unique:users',
            'password' => 'required|confirmed|min:6|case_diff|numbers|letters',
//            'g-recaptcha-response' => 'required|captcha'
        ]);

        event(new Registered($user = $this->create($request->all())));

        Mail::to($request->email)->send(new NewUserRegMail($user));

        event(new UserSignedUp(now()));

        $user->images_remaining = 1000;
        $user->verify_remaining = 5000;
        $user->save();

        return [
            'user_id' => $user->id,
            'email' => $user->email
        ];
    }

   /**
    * The user clicked the confirm email link
    */
    public function confirmEmail ($token)
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
