<?php

namespace App\Http\Controllers;

use Log;
use Auth;
use App\Models\Users\User;
use App\Subscriber;
use Illuminate\Http\Request;

class EmailSubController extends Controller
{

    /**
     * Unsubscribe an Un-authenticated user from email subscriptions via Sent Email
     */
    public function unsubEmail (Request $request, $subToken)
    {
        $user = User::where('sub_token', $subToken)->first();

        if ($user) {
            $user->emailsub = 0;
            $user->save();

            return redirect('/?unsub=1');
        }

        $subscriber = Subscriber::where('sub_token', $subToken)->first();

        if ($subscriber) {
            $subscriber->delete();
        }

        return redirect('/?unsub=1');
    }

    /**
     * Toggle Subscription to Emails
     * Todo - move this data to new user_settings table
     */
    public function toggleEmailSub (Request $request)
    {
        $user = Auth::user();
        $user->emailsub = ! $user->emailsub;
        $user->save();

        return [ 'sub' => $user->emailsub ];
    }

}
