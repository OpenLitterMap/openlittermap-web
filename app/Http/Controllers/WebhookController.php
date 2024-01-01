<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use App\Models\User\User;
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
     * Change "customer.created" to handleCustomerCreated
     */
    public function handleWebhook (Request $request)
    {
        $method = 'handle'.Str::studly(str_replace('.', '_', (string) $request->type));

        if (method_exists($this, $method)) {
            return $this->{$method}($request->all());
        } else {
            return $this->missingMethod();
        }
    }

    /**
     * A new customer has been created
     *
     * This happens second.
     *
     * @param $request
     * @return string[]
     */
    protected function handleCustomerCreated ($request)
    {
        // \Log::info('handleCustomerCreated', $request);

        if ($user = User::where('email', $request['data']['object']['email'])->first())
        {
            $user->stripe_id = $request['data']['object']['id'];
            $user->save();

            return ['status' => 'success'];
        }
    }

    /**
     * Handle a successful payment
     * Not sure why this comes before customer.created, but this is first
     */
    protected function handleChargeSucceeded (array $payload)
    {
        // \Log::info(['handleChargeSucceeded', $payload]);

        if ($user = User::where('email', $payload['data']['object']['billing_details']['email'])->first())
        {
            $user->payments()->create(['amount' => $payload['data']['object']['amount'], 'stripe_id' => $user->stripe_id]);
        }

        return ['status' => 'success'];
    }

    /**
     * Third
     */
    protected function handleCustomerSubscriptionCreated (array $payload)
    {
        // \Log::info(['handleSubscriptionCreated', $payload]);

        if ($user = User::where('stripe_id', $payload['data']['object']['customer'])->first())
        {
            $name = $payload['data']['object']['items']['data'][0]['plan']['nickname'];
            $sub_id = $payload['data']['object']['id']; // sub_id
            $plan_id =  $payload['data']['object']['items']['data'][0]['plan']['id'];

            if (is_null($name)) {
                $name = $payload['data']['object']['items']['data'][0]['plan']['id'];
            }

            $user->subscriptions()->create([
                'name' => $name ?: '', // Startup, Advanced, Pro.
                'stripe_id' => $sub_id,
                'stripe_plan' => $plan_id,
                'quantity' => 1,
                'ends_at' => now()->addMonths(1),
                'stripe_active' => 1,
                'stripe_status' => 'active'
            ]);

            return ['status' => 'success'];
        }

        return false;
    }

    // END CUSTOM EVENTS
    /**
     * Handle a cancelled customer from a Stripe subscription.
     *
     * @return Response
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
     * @return Billable
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
        } catch (Exception $exception) {
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
