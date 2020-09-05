<?php

namespace App\Http\Controllers;

use App\Plan;
use Auth;
use App\User;
use Laravel\Cashier\Cashier;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    /**
     * Get the stripe customer object
     * Incl. active subscriptions, etc
     */
    public function check ()
    {
        $stripe_id = Auth::user()->stripe_id;

        $customer = Cashier::findBillable($stripe_id)->asStripeCustomer();;

        return ['customer' => $customer];
    }


    /**
     * Webhook - A customer has been created
     */
    public function create (Request $request)
    {
        if ($user = User::where('email', $request['data']['object']['email'])->first())
        {
            $user->stripe_id = $request['data']['object']['id'];
            $user->save();

            // we need to get the name of the plan from the customer ?
            $customer = $user->asStripeCustomer();
            $name = $customer->subscriptions->first()->plan->nickname;

            // Doing this manually because laravel cashier is actually a pain
            // and I used this table incorrectly to begin with so it needs to be updated.
            $user->subscriptions()->create([
                'name' => $name,
                'stripe_id' => $customer->subscriptions->data[0]->id, // sub_id
                'stripe_plan' => $name,
                'quantity' => 1,
                'ends_at' => now()->addMonths(1),
                'stripe_active' => 1,
                'stripe_status' => 'active'
            ]);

            return ['status' => 'success'];
        }
    }

    /**
     * The user wants to unsubscribe from payments
     */
    public function delete (Request $request)
    {
        if ($user = Auth::user()->asStripeCustomer())
        {

        }
    }

    /**
     * Webhook - A payment has been successful
     */
    public function payment_success (Request $request)
    {
        if ($user = User::where('email', $request['data']['object']['customer_email'])->first())
        {
            $user->payments()->create(['amount' => $request['data']['object']['amount_paid'], 'stripe_id' => $user->stripe_id]);

            return ['status' => 'success'];
        }
    }
}
