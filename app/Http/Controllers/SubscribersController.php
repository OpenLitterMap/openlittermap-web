<?php

namespace App\Http\Controllers;

use App\Subscriber;
use Illuminate\Http\Request;

class SubscribersController extends Controller
{
    /**
     * Create a new subscriber
     */
    public function create (Request $request)
    {
        $request->validate([
           'email' => 'required|email|unique:subscribers|max:100'
        ]);

        Subscriber::create([
            'email' => $request->email
        ]);

        return ['msg' => 'success'];
    }
}
