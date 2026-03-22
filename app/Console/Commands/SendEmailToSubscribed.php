<?php

namespace App\Console\Commands;

use App\Subscriber;
use App\Models\Users\User;
use App\Jobs\Emails\DispatchEmail;
use Illuminate\Console\Command;

class SendEmailToSubscribed extends Command
{
    protected $signature = 'olm:send-email-to-subscribed
        {--dry-run : Count recipients without sending}
        {--test= : Send a single test email to this address}
        {--chunk=100 : Number of jobs to dispatch per batch}';

    protected $description = 'Send an email update to all subscribed users and subscribers.';

    public function handle(): int
    {
        // ─── Test mode ───────────────────────────────────────────────
        if ($testEmail = $this->option('test')) {
            $this->info("Sending test email to {$testEmail}...");

            $fakeRecipient = (object) [
                'email' => $testEmail,
                'sub_token' => 'test-token-preview',
            ];

            dispatch(new DispatchEmail($fakeRecipient));

            $this->info('Test email dispatched to queue.');
            return self::SUCCESS;
        }

        // ─── Gather recipients ───────────────────────────────────────

        $userCount = User::where('emailsub', 1)->count();

        // Subscribers not already in users table (avoid duplicates)
        $userEmails = User::where('emailsub', 1)->pluck('email')->toArray();
        $subscriberCount = Subscriber::whereNotIn('email', $userEmails)->count();

        $totalCount = $userCount + $subscriberCount;

        $this->info("Recipients:");
        $this->info("  Users (emailsub=1): {$userCount}");
        $this->info("  Subscribers (unique): {$subscriberCount}");
        $this->info("  Total: {$totalCount}");

        // ─── Dry run ─────────────────────────────────────────────────
        if ($this->option('dry-run')) {
            $this->info('Dry run complete — no emails dispatched.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Dispatch {$totalCount} emails to queue?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $chunkSize = (int) $this->option('chunk');
        $dispatched = 0;

        // ─── Users ───────────────────────────────────────────────────
        User::where('emailsub', 1)
            ->orderBy('id')
            ->chunk($chunkSize, function ($users) use (&$dispatched) {
                foreach ($users as $user) {
                    dispatch(new DispatchEmail($user));
                    $dispatched++;
                }
                $this->info("Dispatched {$dispatched} so far...");
            });

        // ─── Subscribers (deduplicated) ──────────────────────────────
        Subscriber::whereNotIn('email', $userEmails)
            ->orderBy('id')
            ->chunk($chunkSize, function ($subscribers) use (&$dispatched) {
                foreach ($subscribers as $subscriber) {
                    dispatch(new DispatchEmail($subscriber));
                    $dispatched++;
                }
                $this->info("Dispatched {$dispatched} so far...");
            });

        $this->info("Done. {$dispatched} emails dispatched to queue.");

        return self::SUCCESS;
    }
}
