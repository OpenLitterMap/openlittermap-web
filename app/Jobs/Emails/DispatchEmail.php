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
use DateTimeInterface;
use Throwable;

/**
 * Sends one campaign email to one recipient and records the outcome in the
 * `email_sends` ledger. One job per recipient — blast radius of a bad address
 * is exactly one job.
 *
 * Ledger writes happen in the terminal hooks: `accepted` on a successful send,
 * `failed` only in failed() (after the retry window / exception budget is
 * exhausted) — never on the first throw. A null $ledgerRowId means an untracked
 * preview (--only-email).
 *
 * Attempts are time-based (retryUntil), not a fixed $tries: the RateLimited
 * middleware releases a throttled job back to the queue and that increments the
 * attempt count, so a fixed $tries would burn through attempts and strand valid
 * recipients as `failed` during a bulk run. Genuine send failures are still
 * bounded by $maxExceptions (a throttle release is not an exception).
 */
class DispatchEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $backoff = [10, 60, 300];

    public int $maxExceptions = 3;

    public function __construct(
        public ?int $ledgerRowId,
        public EmailRecipient $recipient,
    ) {
    }

    public function middleware(): array
    {
        return [new RateLimited('ses-emails')];
    }

    /**
     * Time window for retries. Throttle releases may re-attempt freely within
     * it; once it passes the job fails. Evaluated once at first dispatch.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
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
