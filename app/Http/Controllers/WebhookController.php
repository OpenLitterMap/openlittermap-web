<?php

namespace App\Http\Controllers;

use App\User;
use App\Plan;
use Exception;
// use App\Billing\Payments;
use Illuminate\Http\Request;
// use Stripe\Event as StripeEvent;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Stripe webhook call.
     */
    public function handleWebhook (Request $request)
    {
        // $payload = json_decode($request->getContent(), true);

        $method = 'handle'.studly_case(str_replace('.', '_', $request->type));

        if (method_exists($this, $method)) return $this->{$method}($request->all());

        else return $this->missingMethod();
    }

    /**
     * Handle a successful payment
     */
    protected function handleChargeSucceeded (array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['id']);

        $plans = Plan::all();

        $amount = 0; // payment info
        foreach ($plans as $index => $plan)
        {
            if ($user->onPlan($plan->name))
            {
                // set user limitations
                $amount = $plan->price;
            }
        }

        // Update the payments table
        $user->payments()->create(['amount' => $amount, 'stripe_id' => $user->stripe_id]);

        // Calculate tax, revenue
        return ['status' => 'New Subscription Created'];
    }

    /**
     * A new customer has been created
     */
    protected function handleCustomerCreated ($request)
    {
        \Log::info(['customer.created', $request]);
        if ($user = User::where('email', $request['data']['object']['email'])->first())
        {
            $user->stripe_id = $request['data']['object']['id'];
            $user->save();

            // we need to get the name of the plan from the customer ?
            $customer = $user->asStripeCustomer();
            \Log::info(['customer', $customer]);
            $name = $customer->subscriptions->first()->plan->nickname;
            $plan = $customer->subscriptions->first()->plan->id;

            \Log::info(['name', $name]); // null
            \Log::info(['plan', $plan]); // Pro

            // Doing this manually because
            // 1. laravel cashier is actually a pain to get right
            // 2. and I used this table incorrectly to begin with so it needs to be updated.
            $user->subscriptions()->create([
                'name' => $name, // Startup, Advanced, Pro.
                'stripe_id' => $customer->subscriptions->data[0]->id, // sub_id
                'stripe_plan' => $plan, // plan_id
                'quantity' => 1,
                'ends_at' => now()->addMonths(1),
                'stripe_active' => 1,
                'stripe_status' => 'active'
            ]);

            return ['status' => 'success'];
        }
    }

    // END CUSTOM EVENTS

    /**
     * Handle a cancelled customer from a Stripe subscription.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionDeleted (array $payload)
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
    protected function getUserByStripeId ($stripeId)
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
    protected function eventExistsOnStripe ($id)
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
    protected function isInTestingEnvironment ()
    {
        return getenv('CASHIER_ENV') === 'testing';
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array   $parameters
     * @return mixed
     */
    public function missingMethod ($parameters = [])
    {
        return new Response;
    }
}
