<?php

namespace Tests\Feature\Email;

use App\Jobs\Emails\DispatchEmail;
use App\Mail\EmailUpdate;
use App\Models\EmailSend;
use App\Support\EmailRecipient;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class DispatchEmailTest extends TestCase
{
    private function recipient(string $email = 'foo@bar.com'): EmailRecipient
    {
        return new EmailRecipient('user', 5, $email, 'tok-123');
    }

    private function ledgerRow(string $email = 'foo@bar.com'): EmailSend
    {
        return EmailSend::create([
            'campaign' => 'update28',
            'email' => $email,
            'recipient_type' => 'user',
            'recipient_id' => 5,
            'status' => 'queued',
        ]);
    }

    public function test_handle_sends_mailable_to_recipient(): void
    {
        Mail::fake();

        (new DispatchEmail(null, $this->recipient()))->handle();

        Mail::assertSent(EmailUpdate::class, fn ($m) => $m->hasTo('foo@bar.com'));
    }

    public function test_handle_marks_ledger_row_accepted(): void
    {
        Mail::fake();
        $row = $this->ledgerRow();

        (new DispatchEmail($row->id, $this->recipient()))->handle();

        $row->refresh();
        $this->assertSame('accepted', $row->status);
        $this->assertNotNull($row->accepted_at);
    }

    public function test_handle_with_null_ledger_id_touches_no_ledger(): void
    {
        Mail::fake();

        (new DispatchEmail(null, $this->recipient('preview@bar.com')))->handle();

        $this->assertSame(0, EmailSend::count());
    }

    public function test_failed_hook_marks_ledger_row_failed(): void
    {
        $row = $this->ledgerRow();

        (new DispatchEmail($row->id, $this->recipient()))
            ->failed(new RuntimeException('SES rejected: Invalid domain name'));

        $row->refresh();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->failed_at);
        $this->assertStringContainsString('SES rejected', $row->failure_reason);
    }

    public function test_mailable_renders_recipient_sub_token(): void
    {
        $html = (new EmailUpdate($this->recipient()))->render();

        $this->assertStringContainsString('tok-123', $html);
        $this->assertStringContainsString('unsubscribe', $html);
    }

    /**
     * RateLimited releases a throttled job back to the queue, which increments
     * attempts — a fixed $tries would strand valid recipients as `failed`
     * during a bulk run. Time-based attempts (retryUntil) avoid that; genuine
     * send failures are still bounded by $maxExceptions.
     */
    public function test_uses_time_based_retry_window_not_fixed_tries(): void
    {
        $job = new DispatchEmail(null, $this->recipient());

        $this->assertInstanceOf(\DateTimeInterface::class, $job->retryUntil());
        $this->assertTrue($job->retryUntil() > now());
        $this->assertSame(3, $job->maxExceptions);
    }
}
