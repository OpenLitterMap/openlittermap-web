<?php

namespace App\Http\Controllers;

use App\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscribersController extends Controller
{
    /**
     * Add a visitor to the mailing list. This is a plain newsletter signup for
     * anonymous visitors — it does not assume or touch user accounts.
     *
     * `email:filter` validates via PHP's filter_var, which rejects the
     * single-label-domain addresses (e.g. `foo@8`) that SES rejects at send
     * time — the same underlying check the dispatch guard uses. (Laravel's plain
     * `email` rule is RFC-permissive and would let them through.)
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'max:100', 'email:filter', 'unique:subscribers'],
        ], [
            'email.email' => 'Please enter an email with a valid domain (e.g. name@example.com).',
        ]);

        Subscriber::create([
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
