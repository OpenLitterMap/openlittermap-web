<?php

namespace App\Http\Controllers;

use DB;
use App\Plan;
use Auth;
use App\User;
use Laravel\Cashier\Cashier;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    /**
     * Get the users first (and only) subscription (active/inactive)
     */
    public function subscriptions ()
    {
        $subs = Auth::user()->subscriptions;

        return ['sub' => $subs[0]];
    }

    /**
     * The user wants to unsubscribe from payments
     */
    public function delete (Request $request)
    {
        if ($user = Auth::user())
        {
            $name = $user->subscriptions->first()->name;

            $user->subscription($name)->cancelNow();
        }

        return ['status' => 'success'];
    }

    /**
     * The user already has stripe_id and is a Stripe Customer
     * We want to re-activate their subscription
     */
    public function resubscribe (Request $request)
    {
        if ($user = Auth::user())
        {
            // delete old, cancelled subscription
            DB::table('subscriptions')->where('user_id', $user->id)->delete();


        }
    }
}
