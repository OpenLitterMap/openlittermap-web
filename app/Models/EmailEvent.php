<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit + idempotency record of a verified SES Bounce/Complaint recipient
 * event delivered via SNS. Raw payloads live here only. The (sns_id, email)
 * unique key makes duplicate SNS deliveries harmless.
 *
 * @property string $sns_id
 * @property string $email
 * @property string $type     Bounce|Complaint
 * @property string|null $subtype
 * @property array|null $payload
 */
class EmailEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sns_id',
        'email',
        'type',
        'subtype',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
