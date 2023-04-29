<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BecomeAMerchantController extends Controller
{
    public function __invoke (Request $request)
    {
        \Log::info($request);


        $request->validate([
            'name' => 'required|min:1',
            'address' => 'required|',
            'email' => 'required|email',
        ]);
    }
}
