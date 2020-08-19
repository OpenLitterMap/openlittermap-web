<?php

namespace App\Http\Controllers;

use Log;
use Auth;
use App\User;
use Illuminate\Http\Request;

class EmailSubController extends Controller
{

    /**
     * Unsubscribe an Un-authenticated user from email subscriptions via Sent Email
     */
    public function unsubEmail(Request $request, $subToken)
    {
        $user = User::where('sub_token', $subToken)->first();
        
        $user->emailsub = 0;
        $user->save();

        // Use the same session key as emailconfirmed
        session()->flash('emailconfirmed', 'Your subscription to the good news has been removed. You can reactivate it later if you like!');

        $locale = \Lang::locale();

        return view('layouts.globalmap', compact('locale'));
    }

    /**
     * Toggle Subscription to Emails
     */
    public function toggleEmailSub (Request $request) {
        $user = Auth::user();
        $user->emailsub = ! $user->emailsub;
        $user->save();
        return [ "sub" => $user->emailsub ];
    }

}
