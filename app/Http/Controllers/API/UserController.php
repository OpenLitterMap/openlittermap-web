<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Creates an intent for payment so we can capture the payment
     * method for the user.
     *
     * @param Request $request The request data from the user.
     */
    public function getSetupIntent (Request $request)
    {
        return $request->user()->createSetupIntent();
    }
}
