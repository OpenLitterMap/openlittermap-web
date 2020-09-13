<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Stripe extends Model
{
    protected $table = 'stripe';

    protected $fillable = [
    	'id',
    	'customer_id',
    	'stripe_active',
    	'subscription_end_at',
        'plan'
    ];

    public function user(){
    	return $this->belongsTo('App\Models\User\User');
    }

}
