<?php

namespace App\Billing;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
// Respond to events in the Webhooks Controller
{

    public function owner() {
        return $this->hasOne('App\User');
    }

    protected $table = 'payments';

    protected $fillable = [
    	'stripe_id',
    	'user_id',
    	'amount',
        'created_at',
        'updated_at'
    ];

    // helper method to convert 900 to €9.00
    public function toMoney(){
    	return number_format($this->amount / 100, 2); // $payment->toMoney();
    }

    // Payment::record($user, 900, $charge_id); // cents
    // Payment::recordFromStripe()
    // $user->payments()->create(['amount', 'stripe_id', )
}
