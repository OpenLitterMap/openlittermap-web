<?php

namespace App\Http\Controllers;

use App\Models\Users\User;
use App\Subscriber;
use App\Support\EmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscribersController extends Controller
{
    /**
     * Add a visitor to the mailing list.
     *
     * Deliverability is gated by EmailAddress::isSendable() — the same check
     * the mass-send dispatch guard uses. Laravel's `email` rule is
     * RFC-permissive and accepts single-label domains (e.g. `foo@8`), which
     * SES rejects at send time; the shared check keeps them out of `subscribers`.
     *
     * If the email belongs to a registered user, resubscribe that user
     * (`emailsub = 1`) instead of creating a subscriber row — the send command
     * excludes user-owned subscriber rows, so a standalone row would never be
     * mailed. This honours the opt-in intent of someone re-subscribing via the
     * public footer.
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

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if ($user) {
            $user->emailsub = 1;
            $user->save();
        } else {
            Subscriber::create([
                'email' => $request->email,
            ]);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
