<?php

use Carbon\Carbon;
use App\Subscription;
use App\Billing\Payment;

namespace App\Billing;

trait oldbill {

    // New Stripe methods

	/**
	 * Find the User by Stripe ID 'customer_id'
	 */
    public static function byStripeId($stripeId){
    	return static::where(['customer_id', $stripeId])->firstOrFail();
    }

    public function stripe(){
        return $this->hasOne('App\Stripe');
    }

    // Activate the users account on the Stripe table 
    public function activate($customerId, $plan) {
    	// if ($customerId == null) {
    	// 	$customerId = $this->stripe()->customer_id;
    	// }
        return $this->stripe()->create([
            'customer_id' => $customerId,
        	// 'customer_id' => $customerId ?: $this->stripe()->customer_id,
            'stripe_active' => true,
            'subscription_end_at' => null,
            'plan' => $plan->id
        ]);
    }

    // Deactivate the users account on the Stripe table 
    public function deactivate($endDate = null){
        
        $endDate = $endDate ?: \Carbon\Carbon::now();
        // endDate = Carbon::createFromTimestamp($subscription->current_period_end);

        return $this->stripe()->update([
            'stripe_active' => false,
            'subscription_end_at' => $endDate
        ]);
    }

    /**
    * Is the user subscribed?
    */
    public function isSubscribed(){
        return !! $this->stripe_active; // !! force bool
    }

    // in the view -> @if($user->isSubscribed()) ... 

    /**
     * Subscribe a user 
     */
    public function subscription(){
        return new \App\Subscription($this);
    }

    /**
    * A user has many payments
    */
    public function payments(){
    	return $this->hasMany(Payment::class);
    }


}