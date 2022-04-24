<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    /*
    * Apply middleware to all of these routes
    */
    public function __construct ()
    {
        return $this->middleware('auth');
    }

    /**
     * Get the currently authenticated user on login
     *
     * Eager load any roles assigned to the user
     */
    public function getAuthUser ()
    {
        return Auth::user()->load('roles')->append('xp_redis');
    }

    /**
     * Update the users name, username and email
     *
     * Todo - invalidate email
     *      - send new email
     *      - notify the user
     */
    public function details (Request $request)
    {
        $user = Auth::user();

        $this->validate($request, [
            'name'     => 'min:3|max:25',
            'email'    => ['required', 'email', 'max:75', Rule::unique('users')->ignore($user->id)],
            'username' => ['required', 'min:3', 'max:75', Rule::unique('users')->ignore($user->id)]
        ]);

        $email_changed = false;

        $user->name = $request->name;
        $user->username = $request->username;

        if ($request->email != $user->email)
        {
            $email_changed = true;
            $user->email = $request->email;
            // todo
            // $user->verified = 0;
            // $user->token = str_random(30);
            // Mail::to($request->email)->send(new NewUserRegMail($user));
        }

        $user->save();

        /* If email_changed, we need to tell the user to verify their new email address */
        return ['message' => 'success', 'email_changed' => $email_changed];
    }

    /**
     * The user wants to change their password
     * Todo - Add custom validation to check users password matches before the rest of the validation
     */
    public function changePassword (Request $request)
    {
        $this->validate($request, [
            'oldpassword' => 'required',
            'password' => 'required|confirmed|min:6|case_diff|numbers|letters|symbols'
        ]);

        $user = Auth::user();

        if (\Hash::check($request->input('oldpassword'), $user->password))
        {
            $user->password = $request->password;
            $user->save();

            return ['message' => 'success'];
        }

        return ['message' => 'fail'];
    }

    /*
    * Update the users security settings
    */
    public function updateSecurity(Request $request) {

        $user = Auth::user();

        // return dd($request);

        // public profile not yet configured
        // if($request->has('public_profile')) {
        //     $user->settings->public_profile = $request->public_profile;
        //     $user->save();
        // }
        // if(!$request->has('public_profile')) {
        //     $user->settings->public_profile = $request->public_profile;
        // }

        if($request->has('first_name')) {
            $user->first_name = true;
            $user->save();
        }
        if(!$request->has('first_name')) {
            $user->first_name = false;
            $user->save();
        }

        if($request->has('user_name')) {
            $user->user_name = true;
            $user->save();
        }
        if(!$request->has('user_name')) {
            $user->user_name = false;
            $user->save();
        }

        if($request->has('items_remaining')) {
            $user->items_remaining = true;
            $user->save();
        }
        if(!$request->has('items_remaining')) {
            $user->items_remaining = false;
            $user->save();
        }

        return redirect('/settings#/general');
    }


    /**
     * The user can delete their profile and all associated records.
     */
    public function destroy (Request $request)
    {
        $this->validate($request, [
            'password' => 'required'
        ]);

        $user = Auth::user();

        // Remove user.id from redis leaderboards

        if (\Hash::check($request->password, $user->password))
        {
            // delete their photos, etc
            // maybe don't delete, but remove all personal information and keep user.id
            $user->delete();
            return ['message' => 'success'];
        }

        else return ['message' => 'password'];
    }

    /**
     * Toggle a Users privacy
     */
    public function togglePrivacy (Request $request)
    {
        $user = Auth::user();

        /* Show on maps */
        $user->show_name_maps = $request->show_name_maps;
        $user->show_username_maps = $request->show_username_maps;

        /* Show on leaderboards */
        $user->show_name = $request->show_name;
        $user->show_username = $request->show_username;

        /* Show on createdBy sections of locations the user has added when uploading */
        $user->show_name_createdby = $request->show_name_createdby;
        $user->show_username_createdby = $request->show_username_createdby;

        $user->save();
    }

    /**
     * Log the user out
     */
    public function logout ()
    {
        Auth::logout();

        return redirect('/');
    }

    public function unsubscribeEmail  ($token)
    {
        $user = User::whereToken($token)->firstOrFail();

        $user->emailsub = 0;
        $user->save();

        Alert::message('You are now unsubscribed');
    }

    /**
     * Update the users phone number
     */
    public function phone (Request $request)
    {
        $phoneNumber = $request['phonenumber'];
        $user = Auth::user();
        $user->phone = $phoneNumber;
        $user->save();
    }

    /**
     * Remove a users phone number from the database
     */
    public function removePhone (Request $request)
    {
        $user = Auth::user();
        $user->phone = '';
        $user->save();
    }

    /**
     * Toggle the users items_remaining value (Default = True == Remaining)
     *
     * Todo - move settings to new table
     * and use new picked_up column
     */
    public function togglePresence (Request $request)
    {
        $user = Auth::user();
        $user->items_remaining = ! $user->items_remaining;
        $user->save();

        return [
            'message' => 'success',
            'value'   => $user->items_remaining
        ];
    }

    /**
     * Upload a users profile photo
     */
    public function uploadProfilePhoto (Request $request)
    {
        $file = $request->file('file'); // -> /tmp/php7S8v..

        $dateTime = new DateTime;

        // Create filename and move to AWS S3
        $explode = explode(':', $dateTime);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        $filename = $file->hashName();
        $filepath = $y.'/'.$m.'/'.$d.'/'.$filename;
        $imageName = '';

        if (app()->environment('production')) {
            $s3 = \Storage::disk('s3');
            $s3->put($filepath, file_get_contents($file), 'public');
            $imageName = $s3->url($filepath);
        }
    }
}
