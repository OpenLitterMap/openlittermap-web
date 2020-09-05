<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    /**
     * Webhook - A customer has been created
     */
    public function create (Request $request)
    {
        if ($user = User::where('email', $request['data']['object']['email'])->first())
        {
            $user->stripe_id = $request['data']['object']['id'];
            $user->save();

            return ['status' => 'success'];
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
