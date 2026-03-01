<?php

namespace App\Http\Controllers;

use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    // Auth middleware applied via route groups in routes/api.php (auth:sanctum).
    // Constructor middleware removed — it used the 'web' guard which conflicts
    // with Sanctum token auth from mobile apps.

    /**
     * Update the users name, username and email.
     *
     * @deprecated Use POST /api/settings/update with key/value pairs instead.
     *             This endpoint does not flag username changes for admin review.
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
            'password' => 'required|confirmed|min:5'
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

    // updateSecurity — removed: wrote to non-existent `first_name`/`user_name` columns


    // destroy — removed: had no relationship cleanup. Use DeleteAccountController instead.

    /**
     * Toggle a Users privacy.
     *
     * @deprecated Use individual POST /api/settings/privacy/{toggle} endpoints instead.
     *             This bulk endpoint has no response body and no validation.
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

        /* Prevent admins or other people from tagging your photos for you */
        $user->prevent_others_tagging_my_photos = $request->boolean('prevent_others_tagging_my_photos');

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

        return redirect('/?unsub=1');
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
    public function removePhone(Request $request): array
    {
        $user = Auth::user();
        $user->phone = null;
        $user->save();

        return ['message' => 'success'];
    }

    /**
     * Toggle the user's picked_up preference.
     *
     * @deprecated Use POST /api/settings/update with key='picked_up' instead.
     */
    public function togglePresence (Request $request)
    {
        $user = Auth::user();
        $user->picked_up = ! $user->picked_up;
        $user->save();

        return [
            'message' => 'success',
            'picked_up' => $user->picked_up,
        ];
    }

    /**
     * Upload a users profile photo
     *
     * TODO: Implement proper profile photo upload with image validation,
     * resizing, and S3 storage. The old implementation had broken date
     * parsing and never saved the URL to the user's avatar column.
     */
    public function uploadProfilePhoto(Request $request): \Illuminate\Http\JsonResponse
    {
        abort(501, 'Profile photo upload is not yet implemented.');
    }
}
