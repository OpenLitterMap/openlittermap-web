<?php

namespace Tests\Feature\Email;

use App\Jobs\Emails\DispatchEmail;
use App\Mail\EmailUpdate;
use App\Models\EmailSend;
use App\Models\Users\User;
use App\Subscriber;
use App\Support\EmailRecipient;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailUpdateTest extends TestCase
{
    private const PROMPT = 'Queue these emails now?';

    private function runSend(array $opts = [], string $confirm = 'yes')
    {
        return $this->artisan('olm:send-email-to-subscribed', array_merge(['--campaign' => 'update28'], $opts))
            ->expectsConfirmation(self::PROMPT, $confirm);
    }

    // ─── Mailable ────────────────────────────────────────────────────

    public function test_email_update_has_correct_subject(): void
    {
        $mailable = new EmailUpdate(User::factory()->create());

        $mailable->assertHasSubject('Update 28 - 1st TidyTowns Webinar, 1 Million Hours & EU Presidency');
    }

    public function test_email_update_renders_with_user(): void
    {
        $user = User::factory()->create();

        $html = (new EmailUpdate($user))->render();

        $this->assertStringContainsString('1st TidyTowns Webinar', $html);
        $this->assertStringContainsString($user->sub_token, $html);
        $this->assertStringContainsString('unsubscribe', $html);
    }

    public function test_email_update_renders_with_subscriber(): void
    {
        $subscriber = Subscriber::create(['email' => 'test@example.com']);

        $html = (new EmailUpdate($subscriber))->render();

        $this->assertStringContainsString('1st TidyTowns Webinar', $html);
        $this->assertStringContainsString($subscriber->sub_token, $html);
    }

    // ─── Command: report + dedup ─────────────────────────────────────

    public function test_dry_run_reports_eligible_count_without_dispatching(): void
    {
        Bus::fake();

        User::factory()->count(2)->create(['emailsub' => 1]);
        User::factory()->create(['emailsub' => 0]);

        $this->artisan('olm:send-email-to-subscribed', ['--campaign' => 'update28', '--dry-run' => true])
            ->expectsOutputToContain('Will dispatch now:      2')
            ->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertSame(0, EmailSend::count());
    }

    public function test_unsubscribed_users_are_excluded(): void
    {
        Bus::fake();

        User::factory()->count(2)->create(['emailsub' => 1]);
        User::factory()->create(['emailsub' => 0]);

        $this->runSend()->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 2);
    }

    public function test_unsubscribed_user_with_subscriber_row_is_fully_excluded(): void
    {
        Bus::fake();

        User::factory()->create(['emailsub' => 0, 'email' => 'optout@test.com']);
        Subscriber::create(['email' => 'optout@test.com']);

        $this->runSend()->assertSuccessful();

        Bus::assertNotDispatched(DispatchEmail::class);
    }

    public function test_overlapping_subscriber_excluded_unique_subscriber_sent(): void
    {
        Bus::fake();

        User::factory()->create(['emailsub' => 1, 'email' => 'overlap@test.com']);
        Subscriber::create(['email' => 'overlap@test.com']);
        Subscriber::create(['email' => 'unique@test.com']);

        $this->runSend()->assertSuccessful();

        // user (overlap) + unique subscriber = 2; the overlapping subscriber is a duplicate.
        Bus::assertDispatched(DispatchEmail::class, 2);
        $this->assertSame('subscriber', EmailSend::where('email', 'unique@test.com')->value('recipient_type'));
        $this->assertSame('user', EmailSend::where('email', 'overlap@test.com')->value('recipient_type'));
    }

    public function test_only_email_dispatches_single_preview(): void
    {
        Bus::fake();

        $this->artisan('olm:send-email-to-subscribed', ['--only-email' => 'sean@test.com'])
            ->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
    }

    // ─── Job dispatches mail ─────────────────────────────────────────

    public function test_dispatch_email_job_sends_mailable(): void
    {
        Mail::fake();

        $recipient = new EmailRecipient('user', 1, 'recipient@test.com', 'tok');
        (new DispatchEmail(null, $recipient))->handle();

        Mail::assertSent(EmailUpdate::class, fn ($mail) => $mail->hasTo('recipient@test.com'));
    }
}
