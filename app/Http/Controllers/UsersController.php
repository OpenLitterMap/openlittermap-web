<?php

namespace App\Http\Controllers;

use Alert;
use Auth;
use Image;
use App\User;
// use App\Award;
use App\Plan;
use App\Team;
use Validator;
use App\Photo;
use App\Level;
use App\TeamType;
use JavaScript;
use App\Settings;
use Carbon\Carbon;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Providers\SweetAlertServiceProvider;
use Log;

class UsersController extends Controller
{
    /*
    * Apply middleware to all of these routes
    */
    public function __construct() {
    	return $this->middleware('auth');
    	parent::__construct();
	}

    /*
     * TEST ROUTE
     */
    public function test() {
        $visits = Redis::incr('vists');;
        return view('pages.test', ['visits' => $visits]);
    }

    /**
    * Get the settings page
    */
    public function settings ()
    {
        $user = Auth::user();

        $plan = '';
        $isValid = false;
        $onGracePeriod = '';
        $ends_at = '';

        // If the user does not have a Stripe ID they are on a Free Plan
        if (! $user->stripe_id) {
            $plan = 'Free';
        } else {
            // Before teams there was only 1 subscription but now there is 2 so we must check for each subscription manually...
            foreach($user->subscriptions as $sub) {
                if(($sub->name == 'Startup') || ($sub->name == 'Basic') || ($sub->name == 'Advanced') || ($sub->name == 'Pro')) {
                    // $plan = Plan::where('name', $sub->name)->first();
                    $plan = $sub;
                }
            }

            if ($plan->valid()) {
                $isValid = 1;
            } else {
                $isValid = 0;
            }

            $onGracePeriod = '';
            if($plan->onGracePeriod()) {
                $onGracePeriod = 1;
            }

            $ends_at = $plan->ends_at;

            if ($ends_at != null) {
                $ends_at = $plan->ends_at->diffForHumans();
            }
            // return $ends_at;
            $plan = $plan->name;
        }

        $plans = Plan::all();
        $lang = \App::getLocale();
        $countries = \App\Country::where('manual_verify', 1)->orWhere('shortcode', 'pr')->orderBy('country', 'asc')->get()->pluck('country', 'shortcode');

        return [
            'user' => $user,
            'plan' => $plan,
            'isValid' => $isValid,
            'ongraceperiod' => $onGracePeriod,
            'ends_at' => $ends_at,
            'plans' => $plans,
            'countries' => $countries // for flags
        ];
    }

    /**
     * Pass the authenticated user details to the view 'user.profile'
     */
    public function getProfile() {

        $locale = \App::getLocale();

        // Limit selection of columns
        $user = Auth::user();
        $subscription = '';

        if(!$user->stripe_Id) {
            $subscription = 'Free';
        } else {
            $subscription = $user->subscriptions->name;
        }

        // Check the users level, update xp bar
        $levels = Level::all();
        foreach($levels as $level) {
            if ($user->xp > $level->xp) {
                $user->level = $level->id;
                $user->save();
            }
        }

        if ($user->level == 0) {
            $startingXP = 0;
            $xpNeeded = 10;
        } else {
            // How much XP is needed for the next level?
            $xpNeeded = $levels[$user->level]['xp'];
            // Previous XP for 0-1 effect
            $startingXP = $levels[$user->level-1]['xp'];
        }

        // Get the photos as pagination x 1
        $photos = $user->photos()->where([
            ['verified', 0],
            ['verification', 0]
        ])->paginate(1); // length aware paginator class

        $littercoin = 0;

        // littercoin earned by adding locations to database
        if ($user->littercoin_owed) {
            $littercoin += $user->littercoin_owed;
        }

        // littercoin earned by producing open data
        if ($user->littercoin_allowance) {
            $littercoin += $user->littercoin_allowance;
        }

        return view('user.profile', [
            'user' => $user,
            'photos' => $photos,
            // 'tasks' => $tasks,
            'xpNeeded' => $xpNeeded,
            'startingXP' => $startingXP,
            // 'photosPerMonthString' => $photosPerMonthString,
            // 'awards' => json_encode($awardsArray),
            'subscription' => $subscription,
            'littercoinowed' => $littercoin,
            'locale' => $locale
        ]);

    }

    /*
    * Change the avatar image of the authenticated user ( & pass in the photos again )
    */
    // public function update_avatar(Request $request) {
    //     // Handle the user upload of avatar

    //     $file = $request->file('avatar');

    //     if ($file == null) {
    //         return redirect()->back();
    //     }

    //     $user = Auth::user();
    //     if($request->hasFile('avatar')) {
    //         // the user has decided to upload a new avatar
    //         $avatar = $request->file('avatar');
    //         $filename = time() . $avatar->getClientOriginalName();
    //         // Image::make($avatar->getRealPath())->resize(300, 300)->save(public_path('uploads/' . $user->id . '/avatar' . '/' . $filename));

    //         $user->avatar = $filename;
    //         $user->save();
    //         $file->move('uploads/' . $user->id . '/avatar' . '/', $filename);
    //         // $photos = Photo::orderBy('created_at', 'desc')->paginate(10);
    //     }
    //     return view('user.settings', array('user' => Auth::user()));
    // }


    /*
    * Update the users name, username and email
    */
    public function updateProfile(Request $request) {

        $user = Auth::user();

        if ($request['name']) {
            $this->validate($request, [
                    'name' => 'min:3|max:25',
                'password' => 'required|min:6|case_diff|numbers|letters|symbols',
            ]);
        }

        if ($request['email']) {
            $this->validate($request, [
                   'email' => 'email|max:75|unique:users',
                'password' => 'required|min:6|case_diff|numbers|letters|symbols',
            ]);
        }

        if ($request['username']) {
            $this->validate($request, [
                'username' => 'max:75|unique:users',
                'password' => 'required|min:6|case_diff|numbers|letters|symbols',
            ]);
        }

        if (\Hash::check($request->input('password'), $user->password)) {

            if ($request->has('name')) {
                $user->name = $request->name;
                $user->save();
                return ['message' => 'Success! Your name has been updated.'];
            }
            if ($request->has('username')) {
                $user->username = $request->username;
                $user->save();
                return ['message' => 'Success! Your username has been updated.'];
            }
            if ($request->has('email')) {
                $user->email = $request->email;
                $user->verified = 0;
                $user->token = str_random(30);
                $user->save();
                return ['message' => 'Success! Your email has been updated.'];
                // Mail::to($request->email)->send(new NewUserRegMail($user));
            }
        } else {
            // flash: Wrong password
            return ['message' => 'Invalid password. Please try again.'];
        }
        // return ['message' => 'Sorry, there was a problem. Please try again.'];
        // return redirect()->back();
    }

    /**
     * Update the users password
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

            return ['message' => 'Success! Your password has been updated.'];
        }

        return ['message' => 'You have entered an incorrect password. Please try again.'];
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


    /*
    * The user can delete their profile and all associated records.
    */
    public function destroy(Request $request) {

        $this->validate($request, [
            'password' => 'required'
        ]);

        $user = Auth::user();

        // Remove from any Redis instances


        if (\Hash::check($request->input('password'), $user->password)) {
            $user->delete();
            return ['message' => 'Your account has been deleted.'];
        } else {
            return ['message' => 'Invalid password. Please try again.'];
        }

        $user->delete();
        // Auth::logout();
        return view('pages.locations.welcome');
    }

    /**
     * Toggle a Users privacy
     * todo - move this to SettingsController.
     */
    public function togglePrivacy(Request $request) {

        $user = Auth::user();

        if ($request->mapsName) {
            $user->show_name_maps = true;
        } else {
            $user->show_name_maps = false;
        }

        if ($request->mapsUsername) {
            $user->show_username_maps = true;
        } else {
            $user->show_username_maps = false;
        }

        if ($request->leaderboardsName) {
            $user->show_name = true;
        } else {
            $user->show_name = false;
        }

        if ($request->leaderboardsUsername) {
            $user->show_username = true;
        } else {
            $user->show_username = false;
        }

        if ($request->createdByName) {
            $user->show_name_createdby = true;
        } else {
            $user->show_name_createdby = false;
        }

        if ($request->createdByUsername) {
            $user->show_username_createdby = true;
        } else {
            $user->show_username_createdby = false;
        }


        $user->save();
    }


	/*
    * Redirect the authenticated user to the submit page
    */
    public function submit() {
    	$user = Auth::user();
    	return view('pages.submit', compact('user'));
    }

    /*
    * Log the user out
    * -> probably an improved way of doing this but it works
    */
    public function logout() {
        Auth::logout();
        return redirect('/');
    }

    public function unsubscribeEmail($token){
        $user = User::whereToken($token)->firstOrFail();
        $user->emailsub = 0;
        $user->save();
        Alert::message('You are now unsubscribed');
    }

    /**
     * Update the users phone number
     */
    public function phone(Request $request) {
        $phoneNumber = $request['phonenumber'];
        $user = Auth::user();
        $user->phone = $phoneNumber;
        $user->save();
    }

    /**
     * Remove a users phone number from the database
     */
    public function removePhone(Request $request) {
        $user = Auth::user();
        $user->phone = '';
        $user->save();
    }

    /**
     * Toggle the users items_remaining value (Default = True == Remaining)
     */
    public function togglePresence(Request $request) {
        $user = Auth::user();
        $user->items_remaining = ! $user->items_remaining;
        $user->save();
        // return $user->items_remaining;
    }

    /**
     * Upload a users profile photo
     */
    public function uploadProfilePhoto(Request $request)
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
