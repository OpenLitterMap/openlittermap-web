<?php

namespace App\Jobs\Emails;

use App\Mail\EmailUpdate;
use App\Models\EmailSend;
use App\Support\EmailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one campaign email to one recipient and records the outcome in the
 * `email_sends` ledger. One job per recipient — blast radius of a bad address
 * is exactly one job.
 *
 * Ledger writes happen in the terminal hooks: `accepted` on a successful send,
 * `failed` only in failed() (after $tries is exhausted) — never on the first
 * throw. A null $ledgerRowId means an untracked preview (--only-email).
 */
class DispatchEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public ?int $ledgerRowId,
        public EmailRecipient $recipient,
    ) {
    }

    public function middleware(): array
    {
        return [new RateLimited('ses-emails')];
    }

    public function handle(): void
    {
        $sent = Mail::to($this->recipient->email)->send(new EmailUpdate($this->recipient));

        if ($this->ledgerRowId === null) {
            return;
        }

        EmailSend::query()->whereKey($this->ledgerRowId)->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'provider_message_id' => $sent?->getMessageId(),
            'updated_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        if ($this->ledgerRowId === null) {
            return;
        }

        EmailSend::query()->whereKey($this->ledgerRowId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => mb_substr($e->getMessage(), 0, 255),
            'updated_at' => now(),
        ]);
    }
}
