<?php

namespace App\Http\Controllers;

use App\Subscriber;
use App\Support\EmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscribersController extends Controller
{
    /**
     * Add a visitor to the mailing list. This is a plain newsletter signup for
     * anonymous visitors — it does not assume or touch user accounts.
     *
     * Deliverability is gated by EmailAddress::isSendable() — the same check
     * the mass-send dispatch guard uses. Laravel's `email` rule is
     * RFC-permissive and accepts single-label domains (e.g. `foo@8`), which
     * SES rejects at send time; the shared check keeps them out of `subscribers`.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => [
                'required',
                'max:100',
                'email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! EmailAddress::isSendable((string) $value)) {
                        $fail('Please enter an email with a valid domain (e.g. name@example.com).');
                    }
                },
                'unique:subscribers',
            ],
        ]);

        Subscriber::create([
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
