<?php

namespace Tests\Feature\Email;

use App\Jobs\Emails\DispatchEmail;
use App\Mail\EmailUpdate;
use App\Models\Users\User;
use App\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class V5AnnouncementEmailTest extends TestCase
{
    use RefreshDatabase;

    // ─── Mailable ────────────────────────────────────────────────────

    public function test_email_update_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $mailable = new EmailUpdate($user);

        $mailable->assertHasSubject('Update 27 - Mobile app updates & more!');
    }

    public function test_email_update_renders_with_user(): void
    {
        $user = User::factory()->create();
        $mailable = new EmailUpdate($user);

        $html = $mailable->render();

        $this->assertStringContainsString('New Mobile Apps', $html); // Title in HTML body
        $this->assertStringContainsString($user->sub_token, $html);
        $this->assertStringContainsString('unsubscribe', $html);
    }

    public function test_email_update_renders_with_subscriber(): void
    {
        $subscriber = Subscriber::create(['email' => 'test@example.com']);
        $mailable = new EmailUpdate($subscriber);

        $html = $mailable->render();

        $this->assertStringContainsString('New Mobile Apps', $html); // Title in HTML body
        $this->assertStringContainsString($subscriber->sub_token, $html);
    }

    // ─── Command: dry run ────────────────────────────────────────────

    public function test_dry_run_counts_recipients_without_dispatching(): void
    {
        Bus::fake();

        User::factory()->count(3)->create(['emailsub' => 1]);
        User::factory()->create(['emailsub' => 0]);

        $this->artisan('olm:send-email-to-subscribed --dry-run')
            ->expectsOutputToContain('Users (emailsub=1): 3')
            ->expectsOutputToContain('no emails dispatched')
            ->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    // ─── Command: test mode ──────────────────────────────────────────

    public function test_test_flag_dispatches_single_email(): void
    {
        Bus::fake();

        $this->artisan('olm:send-email-to-subscribed --test=sean@test.com')
            ->expectsOutputToContain('Test email dispatched')
            ->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
    }

    // ─── Command: deduplication ──────────────────────────────────────

    public function test_subscribers_overlapping_with_users_are_excluded(): void
    {
        Bus::fake();

        $user = User::factory()->create(['emailsub' => 1, 'email' => 'overlap@test.com']);
        Subscriber::create(['email' => 'overlap@test.com']);
        Subscriber::create(['email' => 'unique@test.com']);

        $this->artisan('olm:send-email-to-subscribed --dry-run')
            ->expectsOutputToContain('Users (emailsub=1): 1')
            ->expectsOutputToContain('Subscribers (unique): 1')
            ->expectsOutputToContain('Total: 2')
            ->assertSuccessful();
    }

    public function test_unsubscribed_user_with_subscriber_row_is_fully_excluded(): void
    {
        Bus::fake();

        // User opted out (emailsub=0) but also has a row in subscribers table
        $user = User::factory()->create(['emailsub' => 0, 'email' => 'optout@test.com']);
        Subscriber::create(['email' => 'optout@test.com']);

        $this->artisan('olm:send-email-to-subscribed --dry-run')
            ->expectsOutputToContain('Users (emailsub=1): 0')
            ->expectsOutputToContain('Subscribers (unique): 0')
            ->expectsOutputToContain('Total: 0')
            ->assertSuccessful();
    }

    // ─── Command: respects unsubscribe ───────────────────────────────

    public function test_unsubscribed_users_are_excluded(): void
    {
        Bus::fake();

        User::factory()->count(2)->create(['emailsub' => 1]);
        User::factory()->create(['emailsub' => 0]);

        $this->artisan('olm:send-email-to-subscribed --dry-run')
            ->expectsOutputToContain('Users (emailsub=1): 2')
            ->assertSuccessful();
    }

    // ─── Job dispatches mail ─────────────────────────────────────────

    public function test_dispatch_email_job_sends_mailable(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $job = new DispatchEmail($user);
        $job->handle();

        Mail::assertSent(EmailUpdate::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
