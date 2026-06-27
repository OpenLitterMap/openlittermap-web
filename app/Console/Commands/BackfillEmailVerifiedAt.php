<?php

namespace App\Console\Commands;

use App\Models\Users\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill of the new `email_verified_at` column for users who were
 * already verified under the legacy `users.verified` boolean.
 *
 * The true verification moment was never recorded, so `created_at` is the
 * agreed proxy. Dry-run by default (reports the count); pass --apply to write.
 * Idempotent — only touches rows where verified=1 AND email_verified_at IS NULL.
 *
 * Equivalent SQL:
 *   UPDATE users SET email_verified_at = created_at
 *   WHERE verified = 1 AND email_verified_at IS NULL;
 */
class BackfillEmailVerifiedAt extends Command
{
    protected $signature = 'email:backfill-verified-at {--apply : Apply the update (default is a dry-run)}';

    protected $description = 'Backfill users.email_verified_at = created_at for legacy verified users.';

    public function handle(): int
    {
        $pending = User::query()
            ->where('verified', 1)
            ->whereNull('email_verified_at');

        $count = $pending->count();

        if (! $this->option('apply')) {
            $this->info("Dry run: {$count} verified user(s) would get email_verified_at = created_at.");
            $this->line('Re-run with --apply to write.');

            return self::SUCCESS;
        }

        $updated = $pending->update(['email_verified_at' => DB::raw('created_at')]);

        $this->info("Backfilled email_verified_at for {$updated} user(s).");

        return self::SUCCESS;
    }
}
