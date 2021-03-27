<?php

namespace App\Http\Controllers;

use Alert;
use Auth;
use Image;
use App\Models\User\User;
// use App\Award;
use App\Plan;
use App\Team;
use Validator;
use App\Models\Photo;
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
    public function __construct ()
    {
    	return $this->middleware('auth');

    	parent::__construct();
	}

    /**
     * Get the currently authenticated user on login
     *
     * Eager load any roles assigned to the user
     */
    public function getAuthUser ()
    {
        return Auth::user()->load('roles');
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
        $countries = \App\Models\Location\Country::where('manual_verify', 1)->orWhere('shortcode', 'pr')->orderBy('country', 'asc')->get()->pluck('country', 'shortcode');

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


    /**
     * Update the users name, username and email
     *
     * Todo - invalidate email
     *      - send new email
     *      - notify the user
     */
    public function details (Request $request)
    {
        $this->validate($request, [
            'name'     => 'min:3|max:25',
            'email'    => 'email|max:75|unique:users',
            'username' => 'max:75|unique:users',
        ]);

        $user = Auth::user();

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
