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

        // Exclude ALL user emails from subscribers (users control their own sub via emailsub flag)
        $userEmailSubquery = User::withoutGlobalScopes()->select('email');
        $subscriberCount = Subscriber::whereNotIn('email', $userEmailSubquery)->count();

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

        $bar = $this->output->createProgressBar($totalCount);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%");
        $bar->start();

        // ─── Users ───────────────────────────────────────────────────
        User::where('emailsub', 1)
            ->orderBy('id')
            ->chunk($chunkSize, function ($users) use (&$dispatched, $bar) {
                foreach ($users as $user) {
                    dispatch(new DispatchEmail($user));
                    $dispatched++;
                    $bar->advance();
                }
            });

        // ─── Subscribers (deduplicated) ──────────────────────────────
        Subscriber::whereNotIn('email', $userEmailSubquery)
            ->orderBy('id')
            ->chunk($chunkSize, function ($subscribers) use (&$dispatched, $bar) {
                foreach ($subscribers as $subscriber) {
                    dispatch(new DispatchEmail($subscriber));
                    $dispatched++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$dispatched} emails dispatched to queue.");

        return self::SUCCESS;
    }
}
