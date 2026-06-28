<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Campaign ledger row. One per (campaign, normalized email). The unique key
 * is the atomic claim lock; status drives resume/retry behaviour.
 *
 * @property string $campaign
 * @property string $email
 * @property string $recipient_type  user|subscriber
 * @property int|null $recipient_id
 * @property string $status  queued|accepted|failed|skipped_invalid|skipped_suppressed
 */
class EmailSend extends Model
{
    protected $fillable = [
        'campaign',
        'email',
        'recipient_type',
        'recipient_id',
        'status',
        'provider_message_id',
        'accepted_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
