<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User\User;
use App\Plan;
use Exception;
use Illuminate\Http\Request;
// use Stripe\Customer;
// use App\Http\Requests\RegistrationForm;

class SubscriptionsController extends Controller
{

	// reg form will - check auth + validation rules
    public function store (Request $request)
    {
    	// we will only get here is form is valid + authorized

        $plan = Plan::find($request->plan);

    	try {

            $this->validate($request, [
                'stripeEmail' => 'required|email',
                'stripeToken' => 'required',
                       'plan' => 'required'
            ]);

            $user = User::find($request->form['user_id']);

    	} catch(Exception $exception) {
    		// return $e->getMessage();
    		return response()->json(['status' => $exception->getMessage()], 422);
    	}

        $user->newSubscription($plan->name, $plan->name)->create($request->stripeToken);
        // could pass more args here as second item ($token, []..);

    	// return ['status' => 'Success!'];
    }

    /**
     * The user is logged in on Free and wants to upgrade
     */
    public function change (Request $request)
    {

        $plan = Plan::find($request->myplan);

        try {

            $this->validate($request, [
                'stripeEmail' => 'required|email',
                'stripeToken' => 'required',
            ]);

            $user = Auth::user();

        } catch(Exception $exception) {
            // return $e->getMessage();
            return response()->json(['status' => $exception->getMessage()], 422);
        }

        $user->newSubscription($plan->name, $plan->name)->create($request->stripeToken);
    }


    /**
     * Cancel a users subscription (at the end of their subscription) or when they cancel it
     */
    public function destroy (Request $request)
    {
        // check password,

        $this->validate($request, [
            'password' => 'required'
        ]);

        $user = Auth::user();

        if (Hash::check($request->input('password'), $user->password)) {
            // $user->subscriptions[0]->cancel();

            foreach($user->subscriptions as $sub) {
                if (
                    ($sub->name == 'Startup') ||
                    ($sub->name == 'Basic') ||
                    ($sub->name == 'Advanced') ||
                    ($sub->name == 'Pro')
                ) {
                    // $plan = Plan::where('name', $sub->name)->first();
                    $sub->cancel();
                }
            }

            $user->save();
        } else {
            // alert
            return ['message' => 'Invalid password. Please try again.'];
        }

        // redirect with flash
        return ['status' => 'Subscription deleted'];
    }



    /**
     * Resume a Users Subscription
     */
    public function resume (Request $request)
    {
        $user = Auth::user();
        // $user->subscriptions[0]->resume();
        foreach ($user->subscriptions as $sub)
        {
            if (
                ($sub->name == 'Startup') ||
                ($sub->name == 'Basic') ||
                ($sub->name == 'Advanced') ||
                ($sub->name == 'Pro')
            ) {
                $sub->resume();
            }
        }

        $user->save();

        return ['message' => 'Subscription Reactivated!'];
    }

}
