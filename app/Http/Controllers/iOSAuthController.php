<?php

namespace App\Http\Controllers;

// todo, delete this controller.

use Auth;
use Input;
use JWTAuth;
use Validator;
use App\User;
use App\Http\Requests;
use App\Mail\NewUserRegMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Tymon\JWTAuth\Exceptions\JWTException;

class iOSAuthController extends Controller
{
    
    /**
    * Show Login 
    */
    protected function showLoginApp() {
        return "login screen txt";
        return view('auth.login');
    }

    /**
    * Verify email and password
    */
    public function authenticate(Request $request) {
    
    // return ["anything" => "something"];

        // return response()->json([
        //     'test' => 'hello'
        // ]);

        // valid = generates token
        $credentials = $request->only('email', 'password');

        // generate token
        try {
        // attempt to verify the credentials and create a token for the user
            // if (! $token = JWTAuth::attempt($credentials)) {
            if (! $token = JWTAuth::attempt($this->getCredentials($request))) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        // all good so return the token
        return response()->json(compact('token'));
    	// invalid = 401 error: "invalid_credentials"
    }

    /**
     * Get the input from the guest
     */
    protected function getCredentials(Request $request) {
    	return [
    		'email' => $request->input('email'),
    		'password' => $request->input('password'),
    		'verified' => true
    	];
    }


    /**
     * If the user is logged in, return their information 
     * TODO: Create a controller or a function to filter the data sent 
     */
    public function getUser() {

        try {

            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent'], $e->getStatusCode());

        }

        // the token is valid and we have found the user via the sub claim  
        return compact('user');      
    }

    /**
     * Registration
     * show
     */
    public function showRegister() {
        return view('auth.register');
    }

    /**
     * Register a new user
     */
    public function registerFromApp(Request $request) {
        $this->validator($request->all())->validate();

        $email = $request->email;

        $token = $request->token;

        event(new Registered($user = $this->create($request->all())));

        Mail::to($email)->send(new NewUserRegMail($user));

        return view('auth.register');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|min:3|max:25',
            'username' => 'required|min:3|max:20|unique:users',
            'email' => 'required|email|max:75|unique:users',
            'password' => 'required|min:6|case_diff|numbers|letters|symbols',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /*
     * The user clicks the confirm email link
     */
    public function confirmEmail($token) {
        // a dynamic / magic method:
        $user = User::whereToken($token)->firstOrFail()->confirmEmail();
        // sline it 
        // flash()->success('Your email is now confirmed.', 'You may now login.');
        // return view('auth.login');
    }


    public function logout() {
        $token = JWTAuth::getToken();
        JWTAuth::invalidate($token);
    }

    // return logout?


    /*
    * Verify the validity of a token
    */
    public function validateToken() {

        //         $user = JWTAuth::parseToken()->authenticate();

        // return $user;

       //  $user = Auth::user();
       //  $token = JWTAuth::fromUser($user);

       //  return \Response::json([
       //     'data' => [
       //         'email' => $user->email,
       //         'registered_at' => $user->created_at->toDateTimeString()
       //     ]
       // ]);
    }





}
