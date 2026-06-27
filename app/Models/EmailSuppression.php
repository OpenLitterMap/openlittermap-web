<?php

namespace App\Models;

use App\Support\EmailAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Source of truth for undeliverable addresses (one row per normalized email).
 *
 * @property string $email
 * @property string $reason  bounced|complained|manual
 * @property string $source  ses|backfill|manual
 */
class EmailSuppression extends Model
{
    protected $fillable = [
        'email',
        'reason',
        'source',
        'suppressed_at',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
        ];
    }

    /**
     * Upsert a suppression by email, enforcing the precedence rule:
     * a complaint outranks a bounce and must never be downgraded.
     *
     * $suppressedAt is the raw event timestamp (e.g. from SES); it is parsed
     * leniently and ignored if unparseable.
     */
    public static function suppress(
        string $email,
        string $reason,
        string $source,
        ?string $suppressedAt = null
    ): self {
        $email = EmailAddress::normalize($email);
        $at = static::parseTimestamp($suppressedAt);

        $existing = static::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->reason === 'complained' && $reason === 'bounced') {
                return $existing;
            }

            $existing->update([
                'reason' => $reason,
                'source' => $source,
                'suppressed_at' => $at ?? $existing->suppressed_at,
            ]);

            return $existing;
        }

        return static::create([
            'email' => $email,
            'reason' => $reason,
            'source' => $source,
            'suppressed_at' => $at,
        ]);
    }

    private static function parseTimestamp(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
