<?php

namespace App\Http\Controllers;

use App\User;
use App\Plan;
use Exception;
use App\Billing\Payments;
use Illuminate\Http\Request;
use Stripe\Event as StripeEvent;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Stripe webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {

        // dd($request->all());

        $payload = json_decode($request->getContent(), true);

        // return $payload;

        // if (! $this->isInTestingEnvironment() && ! $this->eventExistsOnStripe($payload['id'])) {
        //     return;
        // }

        $method = 'handle'.studly_case(str_replace('.', '_', $payload['type']));

        // return $method;

        if (method_exists($this, $method)) {
            return $this->{$method}($payload);
        } else {
            return $this->missingMethod();
        }
    }

    // CUSTOM EVENTS 

    // 1. When a new user signs up 
    // handleCustomerCreated()
    // object['id'] = customer_id
    // currency
    // email 
    // object['sources']['data'] is empty

    // handleCustomerUpdated()
    // object['id'] = cus_id
    // object['data']['id'] = card id 
    // object['subscriptions'] == empty 

    // handleCustomerSourceCreated() 
    // object['id'] // card_id 
    // object['customer'] // customer_id
    // object['cvc_pass'] = true 
    // object['name'] = email 

    // handleCustomerUpdated() 
    // object['subscriptions']['data']['id'] = subscription_id 
    // object['subscriptions']['data']['current_period_end'] = unix timestamp
    // object['subscriptions']['data']['current_period_start'] = unix timestamp
    // object['subscriptions']['data']['customer'] = customer_id 
    // object['subscriptions']['data']['items']['data']['id'] = subscription item id 
    // object['subscriptions']['data']['items']['data']['object'] = subscription item
    // object['subscriptions']['data']['items']['data']['plan']['id'] = Advanced
    // object['subscriptions']['data']['items']['data']['plan']['object'] = plan
    // object['subscriptions']['data']['items']['data']['plan']['amount'] = 2000
    // object['subscriptions']['data']['items']['data']['plan']['currency'] = eur
    // object['subscriptions']['data']['items']['data']['plan']['interval'] = month
    // object['subscriptions']['data']['items']['data']['plan']['name'] = Advanced
    // object['subscriptions']['data']['items']['data']['plan']['statement_descriptor'] = 20 per month

    // handleInvoiceCreated()
    // object['id'] = invoice_id
    // object['object'] = invoice
    // object['amount_due'] = 2000
    // object['attempted'] = true
    // object['charge'] = charge_id
    // object['customer'] = customer_id
    // object['lines']['data']['period']['start']
    // object['lines']['data']['period']['end']
    // object['lines']['data']['plan']['id'] = Advanced // links to the actual plan
    // object['lines']['data']['plan']['amount'] = 2000
    // object['lines']['data']['plan']['name'] = Advanced

    // handleInvoicePaymentSucceeded() 
    // object['id'] = invoice id 
    // object['amount_due']
    // object['charge'] = charge_id 
    // object['customer'] = customer_id 
    // etc 

    // protected function handleCustomerSubscriptionUpdated(array $payload) {
    //     // 
    // }


    /**
     * Handle a successful payment 
     */
    protected function handleChargeSucceeded(array $payload) {

        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        $plans = Plan::all();

        // Set max image upload
        $amount = 0; // payment info 
        foreach ($plans as $index => $plan) {
            if ($user->onPlan($plan->name)) {
                $user->images_remaining = $plan->images;
                $user->verify_remaining = $plan->verify;
                $user->save();
                $amount = $plan->price;
            }
        }

        // Update the payments table 
        $user->payments()->create(['amount' => $amount, 'stripe_id' => $user->stripe_id]);  

        // Calculate tax, revenue 
        return ['status' => 'New Subscription Created'];

    }


    
    // END CUSTOM EVENTS

    /**
     * Handle a cancelled customer from a Stripe subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            $user->subscriptions->filter(function ($subscription) use ($payload) {
                return $subscription->stripe_id === $payload['data']['object']['id'];
            })->each(function ($subscription) {
                $subscription->markAsCancelled();
            });
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Get the billable entity instance by Stripe ID.
     *
     * @param  string  $stripeId
     * @return \Laravel\Cashier\Billable
     */
    protected function getUserByStripeId($stripeId)
    {
        $model = getenv('STRIPE_MODEL') ?: config('services.stripe.model');
        return (new $model)->where('stripe_id', $stripeId)->first();
    }

    /**
     * Verify with Stripe that the event is genuine.
     *
     * @param  string  $id
     * @return bool
     */
    protected function eventExistsOnStripe($id)
    {
        try {
            return ! is_null(StripeEvent::retrieve($id, config('services.stripe.secret')));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verify if cashier is in the testing environment.
     *
     * @return bool
     */
    protected function isInTestingEnvironment()
    {
        return getenv('CASHIER_ENV') === 'testing';
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}
