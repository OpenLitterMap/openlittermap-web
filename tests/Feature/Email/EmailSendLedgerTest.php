<?php

namespace Tests\Feature\Email;

use App\Jobs\Emails\DispatchEmail;
use App\Models\EmailSend;
use App\Models\EmailSuppression;
use App\Models\Users\User;
use App\Subscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EmailSendLedgerTest extends TestCase
{
    private const PROMPT = 'Queue these emails now?';

    private function runSend(array $opts = [], string $confirm = 'yes')
    {
        $args = array_merge(['--campaign' => 'update28'], $opts);

        return $this->artisan('olm:send-email-to-subscribed', $args)
            ->expectsConfirmation(self::PROMPT, $confirm);
    }

    public function test_a_fresh_eligible_user_is_claimed_and_dispatched(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'fresh@example.com']);

        $this->runSend()->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertDatabaseHas('email_sends', [
            'campaign' => 'update28',
            'email' => 'fresh@example.com',
            'recipient_type' => 'user',
            'status' => 'queued',
        ]);
    }

    public function test_already_accepted_address_is_skipped_on_rerun(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'done@example.com']);
        EmailSend::create([
            'campaign' => 'update28', 'email' => 'done@example.com',
            'recipient_type' => 'user', 'recipient_id' => $user->id, 'status' => 'accepted',
        ]);

        $this->runSend()->assertSuccessful();

        Bus::assertNotDispatched(DispatchEmail::class);
    }

    public function test_interrupted_run_resumes_without_double_send(): void
    {
        Bus::fake();
        User::factory()->create(['emailsub' => 1, 'email' => 'once@example.com']);

        // First run claims + dispatches (Bus::fake means the job never runs, row stays queued).
        $this->runSend()->assertSuccessful();
        // Second run sees the queued row as in-flight and must not dispatch again.
        $this->runSend()->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame(1, EmailSend::where('email', 'once@example.com')->count());
    }

    public function test_invalid_address_logged_skipped_invalid_not_dispatched(): void
    {
        Bus::fake();
        User::factory()->create(['emailsub' => 1, 'email' => 'bad@bad']);

        $this->runSend()->assertSuccessful();

        Bus::assertNotDispatched(DispatchEmail::class);
        $this->assertDatabaseHas('email_sends', [
            'campaign' => 'update28', 'email' => 'bad@bad', 'status' => 'skipped_invalid',
        ]);
    }

    public function test_suppressed_address_logged_skipped_suppressed_not_dispatched(): void
    {
        Bus::fake();
        User::factory()->create(['emailsub' => 1, 'email' => 'gone@example.com']);
        EmailSuppression::suppress('gone@example.com', 'bounced', 'ses');

        $this->runSend()->assertSuccessful();

        Bus::assertNotDispatched(DispatchEmail::class);
        $this->assertDatabaseHas('email_sends', [
            'campaign' => 'update28', 'email' => 'gone@example.com', 'status' => 'skipped_suppressed',
        ]);
    }

    public function test_unsuppressed_address_is_sent_on_rerun(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'back@example.com']);
        // A prior run skipped it as suppressed; the suppression is now gone.
        EmailSend::create([
            'campaign' => 'update28', 'email' => 'back@example.com',
            'recipient_type' => 'user', 'recipient_id' => $user->id, 'status' => 'skipped_suppressed',
        ]);

        $this->runSend()->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame('queued', EmailSend::where('email', 'back@example.com')->value('status'));
    }

    public function test_fixed_invalid_address_is_sent_on_rerun(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'nowvalid@example.com']);
        EmailSend::create([
            'campaign' => 'update28', 'email' => 'nowvalid@example.com',
            'recipient_type' => 'user', 'recipient_id' => $user->id, 'status' => 'skipped_invalid',
        ]);

        $this->runSend()->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame('queued', EmailSend::where('email', 'nowvalid@example.com')->value('status'));
    }

    public function test_failed_rows_only_requeued_with_retry_failed(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'retry@example.com']);
        EmailSend::create([
            'campaign' => 'update28', 'email' => 'retry@example.com',
            'recipient_type' => 'user', 'recipient_id' => $user->id, 'status' => 'failed',
        ]);

        // Without the flag: failed row is terminal, not re-dispatched.
        $this->runSend()->assertSuccessful();
        Bus::assertNotDispatched(DispatchEmail::class);

        // With the flag: re-claimed and dispatched.
        $this->runSend(['--retry-failed' => true])->assertSuccessful();
        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame('queued', EmailSend::where('email', 'retry@example.com')->value('status'));
    }

    public function test_retry_stale_queued_requeues_old_queued_rows(): void
    {
        Bus::fake();
        $user = User::factory()->create(['emailsub' => 1, 'email' => 'stale@example.com']);
        $row = EmailSend::create([
            'campaign' => 'update28', 'email' => 'stale@example.com',
            'recipient_type' => 'user', 'recipient_id' => $user->id, 'status' => 'queued',
        ]);
        // Strand it 2 hours ago.
        EmailSend::whereKey($row->id)->update(['updated_at' => Carbon::now()->subHours(2)]);

        // Fresh-queued default skips it...
        $this->runSend()->assertSuccessful();
        Bus::assertNotDispatched(DispatchEmail::class);

        // ...but --retry-stale-queued reclaims it.
        $this->runSend(['--retry-stale-queued' => 3600])->assertSuccessful();
        Bus::assertDispatched(DispatchEmail::class, 1);
    }

    public function test_same_email_in_both_tables_user_wins_subscriber_counted_duplicate(): void
    {
        Bus::fake();
        User::factory()->create(['emailsub' => 1, 'email' => 'overlap@example.com']);
        Subscriber::create(['email' => 'overlap@example.com']);

        $this->runSend()
            ->expectsOutputToContain('Duplicate skipped:      1')
            ->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame(1, EmailSend::where('email', 'overlap@example.com')->count());
        $this->assertSame('user', EmailSend::where('email', 'overlap@example.com')->value('recipient_type'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        Bus::fake();
        User::factory()->count(2)->create(['emailsub' => 1]);

        $this->artisan('olm:send-email-to-subscribed', ['--campaign' => 'update28', '--dry-run' => true])
            ->expectsOutputToContain('Will dispatch now:')
            ->assertSuccessful();

        Bus::assertNotDispatched(DispatchEmail::class);
        $this->assertSame(0, EmailSend::count());
    }

    public function test_campaign_is_required(): void
    {
        $this->artisan('olm:send-email-to-subscribed')
            ->assertFailed();
    }

    public function test_only_email_dispatches_single_untracked_preview(): void
    {
        Bus::fake();

        $this->artisan('olm:send-email-to-subscribed', ['--only-email' => 'sean@test.com'])
            ->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 1);
        $this->assertSame(0, EmailSend::count());
    }

    public function test_limit_caps_dispatches(): void
    {
        Bus::fake();
        User::factory()->count(5)->create(['emailsub' => 1]);

        $this->runSend(['--limit' => 2])->assertSuccessful();

        Bus::assertDispatched(DispatchEmail::class, 2);
    }
}
