<?php

namespace App\Http\Controllers;

use App\Donate;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use App\Payment;
use Illuminate\Http\Request;

class DonateController extends Controller
{
    /**
     *  Get the donation amounts
     */
    public function index ()
    {
    	return Donate::all();
    }

    public function submit(Request $request)
    {
        try {
            $this->doPayment($request->stripeToken, $request->stripeEmail, $request->amount);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return ['message' => 'Success!'];
    }

    protected function doPayment($token, $email, $amount)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $customer = Customer::create(array(
            'email' => $email,
            'card'  => $token
        ));
        $charge = Charge::create(array(
            'customer' => $customer->id,
            'amount'   => $amount,
            'currency' => 'eur'
        ));
    }
}
