<?php

namespace App\Http\Controllers;

use App\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @deprecated Route commented out. Mailing list subscribe removed.
 */
class SubscribersController extends Controller
{
    /**
     * Create a new subscriber
     */
    public function __invoke (Request $request): JsonResponse
    {
        $request->validate([
           'email' => 'required|email|unique:subscribers|max:100'
        ]);

        Subscriber::create([
            'email' => $request->email
        ]);

        return response()->json([
            'success' => true
        ]);
    }
}
