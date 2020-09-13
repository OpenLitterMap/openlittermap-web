<?php

namespace App\Http\Controllers;

use DB;
use App\Plan;
use Auth;
use App\Models\User\User;
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
     *
     * todo  https://stripe.com/docs/api/subscriptions/create
     */
    public function resubscribe (Request $request)
    {
        if ($user = Auth::user())
        {
            $plan = Plan::where('name', $request->plan)->first()->plan_id;

            // create a new subscription and manage multiple subscriptions.
            // always get the first sub that is active.

            // array of plan.plan_id
            $plans = ['plan_E579ju4xamcU41', 'Basic', 'Advanced', 'Pro'];

            $user->newSubscription($plan, $plans);

        }
    }
}
