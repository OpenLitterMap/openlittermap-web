<?php

namespace App\Support;

/**
 * Single source of truth for "is this address worth sending to?".
 *
 * Used by the subscribe endpoint (keeps undeliverable addresses out of the
 * `subscribers` table) and the mass-send dispatch guard (skips them at send
 * time). filter_var alone is RFC-permissive and accepts single-label domains
 * like `foo@8`, which SES rejects — the dotted-domain check rejects every
 * known-bad row from the Update 28 failure. No DNS/MX lookups: too slow and
 * flaky for bulk sending.
 */
final class EmailAddress
{
    public static function isSendable(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL)
            && preg_match('/@[^@\s]+\.[a-z]{2,}$/i', $email) === 1;
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
