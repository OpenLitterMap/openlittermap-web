<?php

namespace App\Support;

/**
 * Explicit recipient value object so the mail job never assumes a `User`.
 * Users and subscribers each carry their own `sub_token` for the unsubscribe
 * link; the property is named `sub_token` so EmailUpdate's view (which reads
 * `$user->sub_token`) works for users, subscribers, and this DTO alike.
 */
final class EmailRecipient
{
    public function __construct(
        public string $type,        // user | subscriber
        public int $id,
        public string $email,
        public string $sub_token,   // token for the unsubscribe link
    ) {
    }
}
