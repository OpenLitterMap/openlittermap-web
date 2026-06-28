<?php

namespace App\Support;

/**
 * Email helpers shared across the deliverability code.
 */
final class EmailAddress
{
    /**
     * Deliverability check for the send-time guard (a non-validation context).
     * filter_var rejects malformed and single-label-domain addresses like the
     * `Estherbarriga@8` that broke the Update 28 send — no DNS/MX lookups, which
     * are too slow/flaky for bulk. The subscribe endpoint applies the same
     * underlying check via the `email:filter` validation rule.
     */
    public static function isSendable(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Canonical form used as the key in email_suppressions / email_sends and
     * for dispatch-guard lookups. No Gmail dot/plus canonicalisation.
     */
    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
